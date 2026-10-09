<?php

namespace App\Http\Controllers;

use App\EntryStatus;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Support\BusinessStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Owner sees the full business summary; partner sees only their own.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isOwner()) {
            return view('dashboard.owner', [
                'stats' => BusinessStats::all(),
                'pending' => [
                    'expenses' => Expense::query()->pending()->count(),
                    'purchases' => Purchase::query()->pending()->count(),
                    'sales' => Sale::query()->pending()->count(),
                ],
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
            'stats' => BusinessStats::all($user->id),
            'pendingCount' => $user->pendingEntriesCount(),
            'commissionRate' => (float) $user->commission_rate,
        ]);
    }
}
