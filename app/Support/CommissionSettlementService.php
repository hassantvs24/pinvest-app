<?php

namespace App\Support;

use App\EntryStatus;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\Investment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Owner-managed commission periods.
 *
 * The owner OPENS a period (optionally recording opening cash and an
 * opening investment) and later CLOSES it manually. At closing, the
 * period profit (confirmed sales - purchases - expenses, by entry date)
 * is computed and each partner's settlement is created:
 *
 *     settlement = period profit x partner commission rate %
 *
 * Periods with profit <= 0 produce no settlements (loss periods).
 * One settlement per partner per period (unique guard) — closing twice
 * is harmless.
 */
class CommissionSettlementService
{
    /**
     * Open a new commission period. Optionally records an opening
     * investment entry at the same time.
     */
    public static function openPeriod(?string $label, Carbon $openedAt, ?float $openingCash, ?float $investmentAmount, ?string $note): CommissionPeriod
    {
        if ($investmentAmount !== null && $investmentAmount > 0) {
            Investment::query()->create([
                'amount' => $investmentAmount,
                'note' => __('messages.opening_investment_note', ['label' => $label ?? $openedAt->format('d M Y')]),
                'invested_at' => $openedAt->toDateString(),
            ]);
        }

        return CommissionPeriod::query()->create([
            'label' => $label,
            'opened_at' => $openedAt,
            'opening_cash' => $openingCash,
            'note' => $note,
            'status' => 'open',
        ]);
    }

    /**
     * Preview rows for closing: what each active partner would receive for
     * the given profit, using their rate RIGHT NOW (the same source the
     * actual close uses, so preview and result always match).
     *
     * @return array<int, array{user: User, rate: float, amount: float}>
     */
    public static function settlementRows(float $profit): array
    {
        if ($profit <= 0) {
            return [];
        }

        $rows = [];

        foreach (User::query()->partners()->active()->where('commission_rate', '>', 0)->get() as $partner) {
            $amount = round($profit * ((float) $partner->commission_rate) / 100, 2);

            if ($amount <= 0) {
                continue;
            }

            $rows[] = ['user' => $partner, 'rate' => (float) $partner->commission_rate, 'amount' => $amount];
        }

        return $rows;
    }

    public static function closePeriod(CommissionPeriod $period, Carbon $closedAt): array
    {
        $profit = self::periodProfit($period->opened_at, $closedAt);

        $period->update([
            'status' => 'closed',
            'profit' => $profit,
            'closed_at' => $closedAt,
        ]);

        $created = 0;

        foreach (self::settlementRows($profit) as $row) {
            $settlement = CommissionSettlement::query()->firstOrCreate(
                [
                    'user_id' => $row['user']->id,
                    'commission_period_id' => $period->id,
                ],
                [
                    'period_start' => $period->opened_at->copy(),
                    'period_end' => $closedAt->copy(),
                    'business_profit' => $profit,
                    'commission_rate' => $row['rate'],
                    'amount' => $row['amount'],
                    'status' => 'pending',
                ],
            );

            if ($settlement->wasRecentlyCreated) {
                $created++;
            }
        }

        return ['profit' => $profit, 'created' => $created];
    }

    /**
     * Confirmed-only net profit between two dates (by entry date).
     */
    public static function periodProfit(Carbon $start, Carbon $end): float
    {
        $sales = (float) Sale::query()
            ->where('status', EntryStatus::Confirmed->value)
            ->whereDate('entry_date', '>=', $start)
            ->whereDate('entry_date', '<=', $end)
            ->sum('total');

        $purchase = (float) Purchase::query()
            ->where('status', EntryStatus::Confirmed->value)
            ->whereDate('entry_date', '>=', $start)
            ->whereDate('entry_date', '<=', $end)
            ->sum('total');

        $expense = (float) Expense::query()
            ->where('status', EntryStatus::Confirmed->value)
            ->whereDate('entry_date', '>=', $start)
            ->whereDate('entry_date', '<=', $end)
            ->sum('amount');

        return $sales - $purchase - $expense;
    }

    /**
     * All pending (owner-unapproved) entries across the three entry types,
     * with user + item/head eager-loaded. A cycle cannot be closed while
     * any of these exist — only one cycle is open at a time, so every
     * pending entry belongs to it.
     *
     * Each row is tagged with `type_label`, `type_icon` and
     * `type_item_relation` (from the shared EntryTypes config) for display.
     *
     * @return Collection<int, Sale|Purchase|Expense>
     */
    public static function pendingEntries(): Collection
    {
        $entries = collect();

        foreach (EntryTypes::all() as $config) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $config['model'];

            $rows = $modelClass::query()
                ->where('status', EntryStatus::Pending->value)
                ->with(['user', $config['item_relation']])
                ->get()
                ->each(function (Model $entry) use ($config): void {
                    $entry->type_label = __('messages.'.$config['label']);
                    $entry->type_icon = $config['icon'];
                    $entry->type_item_relation = $config['item_relation'];
                });

            $entries = $entries->merge($rows);
        }

        return $entries
            ->sortByDesc(fn (Model $entry) => $entry->entry_date->getTimestamp())
            ->values();
    }

    /**
     * Pending commission due for a partner (sum of pending settlements).
     */
    public static function pendingDue(int $userId): float
    {
        return (float) CommissionSettlement::query()
            ->pending()
            ->where('user_id', $userId)
            ->sum('amount');
    }

    /**
     * Total commission earned by a partner (all settlements, any status).
     */
    public static function earned(int $userId, ?string $from = null, ?string $to = null): float
    {
        return (float) CommissionSettlement::query()
            ->where('user_id', $userId)
            ->periodBetween($from, $to)
            ->sum('amount');
    }

    /**
     * Sum of all active partners' commission rates (for the >100% hint).
     */
    public static function totalActiveRate(): float
    {
        return (float) User::query()->partners()->active()->sum('commission_rate');
    }
}
