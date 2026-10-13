<?php

namespace App\Http\Controllers\Owner;

use App\Enums\CashMovementType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CommissionSettlement;
use App\Models\PartnerCashMovement;
use App\Models\User;
use App\Support\BusinessStats;
use App\Support\CashLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Owner-only: track cash that physically sits with a partner (sales
 * money kept, cash handed over incl. investment money for daily
 * operations). Custody only — cash in hand is never changed by these
 * movements; the money still belongs to the business.
 */
class PartnerCashController extends Controller
{
    /**
     * Ledger with per-partner outstanding summary.
     */
    public function index(): View
    {
        return view('owner.partner-cash', [
            'movements' => PartnerCashMovement::query()->with('user')->latest('occurred_at')->latest('id')->paginate(15),
            'partners' => User::query()->partners()->active()->orderBy('name')->get(),
            'outstanding' => PartnerCashMovement::query()
                ->select('user_id')
                ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE -amount END) as net', [CashMovementType::Hold->value])
                ->groupBy('user_id')
                ->pluck('net', 'user_id'),
            'cashInHand' => BusinessStats::all()['cash_in_hand'],
            'cashWithPartners' => PartnerCashMovement::outstanding(),
            'pendingSettlements' => CommissionSettlement::query()
                ->pending()
                ->with('user', 'period')
                ->orderByDesc('period_start')
                ->get(),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Record cash that went to the partner: sales money the partner
     * kept, or cash the owner handed over (incl. investment money for
     * daily operations).
     */
    public function storeHold(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', $this->activePartnerRule()],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['occurred_at'] = $this->occurredMoment($validated['occurred_at']);

        PartnerCashMovement::query()->create([...$validated, 'type' => CashMovementType::Hold]);

        return back()->with('success', __('messages.partner_cash_hold_added'));
    }

    /**
     * Record cash the partner handed back. Cannot exceed what the
     * partner currently holds (the outstanding figure is an aggregate
     * SUM with no row to lock, so the check is serialized via the
     * cash lock).
     */
    public function storeReturn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', $this->activePartnerRule()],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        CashLock::acquire();

        try {
            DB::transaction(function () use ($validated): void {
                $outstanding = PartnerCashMovement::outstanding((int) $validated['user_id']);

                if ((float) $validated['amount'] > $outstanding) {
                    throw ValidationException::withMessages([
                        'amount' => __('messages.partner_cash_exceeds_outstanding', [
                            'cash' => number_format($outstanding, 2),
                        ]),
                    ]);
                }

                $validated['occurred_at'] = $this->occurredMoment($validated['occurred_at']);

                PartnerCashMovement::query()->create([...$validated, 'type' => CashMovementType::Return]);
            });
        } finally {
            CashLock::release();
        }

        return back()->with('success', __('messages.partner_cash_returned'));
    }

    /**
     * Manual cash adjustment: increases (+) or decreases (−) the cash a
     * partner holds. Both directions are unconditional — an adjustment
     * is a deliberate correction, so it may drive the outstanding
     * negative (the owner tops up later) or positive at will.
     */
    public function storeCashAdjustment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', $this->activePartnerRule()],
            'direction' => ['required', Rule::in(['add', 'subtract'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $isAdd = $validated['direction'] === 'add';

        DB::transaction(function () use ($validated, $isAdd): void {
            PartnerCashMovement::query()->create([
                'user_id' => $validated['user_id'],
                'type' => $isAdd ? CashMovementType::Hold : CashMovementType::Spent,
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?? __('messages.cash_adjustment_note', [
                    'sign' => $isAdd ? '+' : '−',
                    'amount' => number_format((float) $validated['amount'], 2),
                ]),
                'occurred_at' => $this->occurredMoment($validated['occurred_at']),
            ]);
        });

        return back()->with('success', __('messages.partner_cash_spent_recorded'));
    }

    /**
     * Settle one pending commission settlement with cash the partner
     * holds: the settlement is marked paid (no payout leaves the drawer
     * — the cash is already with the partner). Full amount only; any
     * remainder of the held cash comes back via a return.
     */
    public function storeAdjustment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'commission_settlement_id' => ['required', 'integer', Rule::exists('commission_settlements', 'id')],
            'occurred_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        CashLock::acquire();

        try {
            DB::transaction(function () use ($validated): void {
                $settlement = CommissionSettlement::query()
                    ->whereKey($validated['commission_settlement_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($settlement->status === 'pending', 409);

                $amount = (float) $settlement->amount;
                $outstanding = PartnerCashMovement::outstanding((int) $settlement->user_id);

                if ($amount > $outstanding) {
                    throw ValidationException::withMessages([
                        'commission_settlement_id' => __('messages.partner_cash_exceeds_outstanding', [
                            'cash' => number_format($outstanding, 2),
                        ]),
                    ]);
                }

                $settlement->update(['status' => 'paid']);

                PartnerCashMovement::query()->create([
                    'user_id' => $settlement->user_id,
                    'type' => CashMovementType::CommissionAdjustment,
                    'amount' => $amount,
                    'note' => $validated['note'] ?? __('messages.commission_adjustment_for', [
                        'period' => $settlement->period?->label ?? $settlement->period_start->format('F Y'),
                    ]),
                    'occurred_at' => $this->occurredMoment($validated['occurred_at']),
                    'commission_settlement_id' => $settlement->id,
                ]);
            });
        } finally {
            CashLock::release();
        }

        return back()->with('success', __('messages.partner_cash_adjusted'));
    }

    /**
     * The movement moment follows the entry convention: picking today
     * records the exact current moment; a past day records that day's
     * start (only the day is chosen, the time is automatic).
     */
    private function occurredMoment(string $date): Carbon
    {
        $picked = Carbon::parse($date);

        return $picked->toDateString() === now()->toDateString()
            ? now()
            : $picked->startOfDay();
    }

    /**
     * Validation rule: the id must be an ACTIVE partner account.
     */
    private function activePartnerRule(): Exists
    {
        return Rule::exists('users', 'id')->where(function ($query): void {
            $query->where('role', UserRole::Partner->value)->where('is_active', true);
        });
    }
}
