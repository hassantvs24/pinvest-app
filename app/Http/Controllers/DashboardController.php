<?php

namespace App\Http\Controllers;

use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\PayoutRequest;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockLoss;
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

        // While a cycle runs, dashboards show that cycle's figures
        // (effective start → today); without one they show lifetime
        // totals. The effective start follows the latest closed cycle,
        // so a same-day reopen never double-counts.
        $from = $openPeriod
            ? CommissionSettlementService::effectiveStart($openPeriod)->format('Y-m-d')
            : null;
        $to = $openPeriod ? now()->format('Y-m-d') : null;
        $profit = $openPeriod ? CommissionSettlementService::runningProfit($openPeriod) : null;

        $cycleData = [
            'openPeriod' => $openPeriod,
            'openDays' => $openPeriod ? max(1, (int) $openPeriod->opened_at->diffInDays(now()) + 1) : 0,
            'commissionEstimated' => $openPeriod !== null,
        ];

        if ($user->isOwner()) {
            $stats = BusinessStats::all(null, $from, $to);

            if ($openPeriod) {
                $stats['commission'] = CommissionSettlementService::estimateCommission($profit, CommissionSettlementService::totalActiveRate());
                $stats['net_profit'] = $profit - $stats['commission'];
            }

            return view('dashboard.owner', [
                ...$cycleData,
                'stats' => $stats,
                'cashInHand' => BusinessStats::all()['cash_in_hand'],
                'pending' => [
                    'expenses' => Expense::query()->pending()->count(),
                    'purchases' => Purchase::query()->pending()->count(),
                    'sales' => Sale::query()->pending()->count(),
                    'productions' => Production::query()->pending()->count(),
                    'stock_losses' => StockLoss::query()->pending()->count(),
                ],
                'pendingPayoutRequests' => PayoutRequest::query()->pending()->count(),
                'leaderboard' => User::query()
                    ->partners()
                    ->orderBy('name')
                    ->get()
                    // Active partners first; inactive (grayed out) sink to
                    // the bottom but stay visible with their history.
                    ->sortBy(fn (User $partner): int => $partner->is_active ? 0 : 1)
                    ->values()
                    ->map(fn (User $partner) => [
                        'user' => $partner,
                        'name' => $partner->name,
                        'is_active' => (bool) $partner->is_active,
                        'commission_rate' => (float) $partner->commission_rate,
                        'total_sales' => $openPeriod
                            ? (float) $partner->sales()->confirmed()->whereDate('entry_date', '>=', $from)->sum('total')
                            : $partner->confirmedSalesTotal(),
                        'total_purchase' => $openPeriod
                            ? (float) $partner->purchases()->confirmed()->whereDate('entry_date', '>=', $from)->sum('total')
                            : $partner->confirmedPurchasesTotal(),
                        'total_expense' => $openPeriod
                            ? (float) $partner->expenses()->confirmed()->whereDate('entry_date', '>=', $from)->sum('amount')
                            : $partner->confirmedExpensesTotal(),
                        // Commission value: estimated while the cycle runs,
                        // lifetime earned settlements otherwise.
                        'commission' => $openPeriod
                            ? CommissionSettlementService::estimateCommission($profit, (float) $partner->commission_rate)
                            : CommissionSettlementService::earned($partner->id),
                    ]),
            ]);
        }

        $stats = BusinessStats::all($user->id, $from, $to);

        if ($openPeriod) {
            $stats['commission'] = CommissionSettlementService::estimateCommission($profit, (float) $user->commission_rate);
        }

        return view('dashboard.partner', [
            ...$cycleData,
            'stats' => $stats,
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
