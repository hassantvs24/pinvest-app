<?php

namespace App\Http\Controllers;

use App\EntryStatus;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\PayoutRequest;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Support\BusinessStats;
use App\Support\CommissionSettlementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Owner sees the full business summary; partner sees only their own.
     * Both see the currently open commission cycle (read-only for partner).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $openPeriod = CommissionPeriod::query()->open()->latest('id')->first();

        $cycleData = [
            'openPeriod' => $openPeriod,
            'openDays' => $openPeriod ? max(1, (int) $openPeriod->opened_at->diffInDays(now()) + 1) : 0,
        ];

        if ($user->isOwner()) {
            return view('dashboard.owner', [
                ...$cycleData,
                'stats' => BusinessStats::all(),
                'pending' => [
                    'expenses' => Expense::query()->pending()->count(),
                    'purchases' => Purchase::query()->pending()->count(),
                    'sales' => Sale::query()->pending()->count(),
                ],
                'pendingPayoutRequests' => PayoutRequest::query()->pending()->count(),
                'leaderboard' => User::query()
                    ->partners()
                    ->withCount(['sales as confirmed_sales_count' => fn ($q) => $q->where('status', EntryStatus::Confirmed->value)])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (User $partner) => [
                        'name' => $partner->name,
                        'commission_rate' => (float) $partner->commission_rate,
                        'total_sales' => $partner->confirmedSalesTotal(),
                    ]),
            ]);
        }

        return view('dashboard.partner', [
            ...$cycleData,
            'stats' => BusinessStats::all($user->id),
            'pendingCount' => $user->pendingEntriesCount(),
            'commissionRate' => (float) $user->commission_rate,
            'pendingCommission' => CommissionSettlementService::pendingDue($user->id),
            'lastSettlement' => CommissionSettlement::query()
                ->with('period')
                ->where('user_id', $user->id)
                ->orderByDesc('period_start')
                ->orderByDesc('id')
                ->first(),
        ]);
    }
}
