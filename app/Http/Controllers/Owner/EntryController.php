<?php

namespace App\Http\Controllers\Owner;

use App\Enums\CashMovementType;
use App\Enums\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\ExpenseHead;
use App\Models\Item;
use App\Models\PartnerCashMovement;
use App\Models\User;
use App\Support\BusinessStats;
use App\Support\CommissionSettlementService;
use App\Support\EntryTypes;
use App\Support\InventoryService;
use App\Support\ItemUnits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Owner-only: view all partners' entries, confirm/reject, edit, hard delete.
 */
class EntryController extends Controller
{
    /**
     * Find an entry of the given type or 404.
     */
    private function findEntry(string $type, int $id, bool $lock = false): Model
    {
        $config = EntryTypes::config($type);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        return $modelClass::query()
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->findOrFail($id);
    }

    /**
     * Entries of one type with the partner + status filters applied.
     *
     * @return Builder<Model>
     */
    private function filteredEntriesQuery(string $type, string $status, int $partnerId): Builder
    {
        $config = EntryTypes::config($type);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        $query = $modelClass::query()->with($config['relations'])->latest('entry_date')->latest('id');

        if ($type === 'expenses') {
            $query->with('item');
        }

        if (in_array($status, ['pending', 'confirmed', 'rejected'], true)) {
            $query->where('status', $status);
        }

        if ($partnerId > 0) {
            $query->where('user_id', $partnerId);
        }

        return $query;
    }

    /**
     * All entries across the three types, merged newest-first into one
     * in-memory paginator (the type filter's "all" option).
     */
    private function allEntries(Request $request, string $status, int $partnerId): LengthAwarePaginator
    {
        $merged = collect(EntryTypes::all())
            ->keys()
            ->flatMap(fn (string $type) => $this->filteredEntriesQuery($type, $status, $partnerId)->get())
            ->sortByDesc(fn (Model $entry) => [$entry->entry_date->getTimestamp(), $entry->id])
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;

        return new LengthAwarePaginator(
            $merged->forPage($page, $perPage)->values()->all(),
            $merged->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * All entries of the chosen type ("all" = every type) with partner +
     * status filters.
     */
    public function index(Request $request): View
    {
        $type = $request->query('type', 'all');
        $status = $request->query('status', 'all');
        $partnerId = (int) $request->query('partner_id', 0);

        $entries = $type === 'all'
            ? $this->allEntries($request, $status, $partnerId)
            : $this->filteredEntriesQuery($type, $status, $partnerId)->paginate(15)->withQueryString();

        return view('owner.entries', [
            'type' => $type,
            'entries' => $entries,
            'status' => $status,
            'partnerId' => $partnerId,
            'partners' => User::query()->partners()->orderBy('name')->get(),
            // Active master items for the inline edit dropdowns, per type.
            'itemsByType' => [
                'expenses' => ExpenseHead::query()->active()->orderBy('name')->get(),
                'purchases' => Item::query()->active()->orderBy('name')->get(),
                'sales' => Item::query()->active()->orderBy('name')->get(),
            ],
            'allItems' => Item::query()->active()->orderBy('name')->get(),
            // Cash position, so approving a purchase/expense can warn about
            // an overdraw right in the confirmation dialog.
            'cashInHand' => BusinessStats::all()['cash_in_hand'],
        ]);
    }

    /**
     * Whether a commission cycle is currently open (entries can only be
     * acted on while one is open).
     */
    private function cycleOpen(): bool
    {
        return CommissionPeriod::query()->open()->exists();
    }

    /**
     * An entry made on/before the latest cycle close belongs to that
     * closed cycle — its numbers are baked into the stored profit and
     * settlements, so it must stay untouched (edit AND delete).
     */
    private function closedCycleEntryError(Model $model): ?string
    {
        $latestClosed = CommissionSettlementService::latestClosedDate();

        if ($latestClosed !== null && $model->entry_date->lessThanOrEqualTo($latestClosed)) {
            return __('messages.entry_locked_closed_cycle');
        }

        return null;
    }

    /**
     * Entry-date rules for edits: today or any past day, never on/inside
     * a closed cycle (backdated entries land at midnight of the picked
     * day, so the day right after a close is the earliest backdate).
     *
     * @return array<int, string>
     */
    private function entryDateRules(): array
    {
        $rules = ['required', 'date', 'before_or_equal:'.now()->toDateString()];

        $latestClosed = CommissionSettlementService::latestClosedDate();
        $openPeriod = CommissionPeriod::query()->open()->latest('id')->first();

        if ($latestClosed !== null) {
            $rules[] = 'after:'.$latestClosed->toDateString();
        } elseif ($openPeriod !== null) {
            $rules[] = 'after_or_equal:'.$openPeriod->opened_at->toDateString();
        }

        return $rules;
    }

    /**
     * Resolves the edit form's date: an unchanged day keeps the stored
     * moment (exact time preserved), a changed day lands at that day's
     * midnight.
     */
    private function editedEntryMoment(Model $model, string $date): Carbon
    {
        $picked = Carbon::parse($date);

        if ($picked->toDateString() === $model->entry_date->toDateString()) {
            return $model->entry_date->copy();
        }

        return $picked->startOfDay();
    }

    /**
     * Stock-overdraft error message when a sale no longer fits, or null
     * when it does. Pending sales reserve stock, so available stock is
     * on hand minus the other pending sales of the same item.
     */
    private function stockOverflowError(string $type, int $itemId, float $quantity, ?int $excludeSaleId = null): ?string
    {
        if ($type !== 'sales') {
            return null;
        }

        $item = Item::query()->findOrFail($itemId);
        $available = InventoryService::availableQuantity($item, $excludeSaleId);

        if (ItemUnits::toBase((float) $quantity, $item->unit) <= $available + 1e-9) {
            return null;
        }

        return __('messages.sale_exceeds_stock', [
            'available' => rtrim(rtrim(number_format(ItemUnits::fromBase(max(0.0, $available), $item->unit), 2), '0'), '.'),
            'unit' => ItemUnits::label($item->unit),
        ]);
    }

    /**
     * Confirm a pending entry.
     */
    public function confirm(string $type, int $entry, Request $request): RedirectResponse
    {
        if (! $this->cycleOpen()) {
            return back()->with('warning', __('messages.confirm_blocked_no_period'));
        }

        $model = null;
        $error = null;

        // Lock the row so two simultaneous confirms cannot both pass
        // the stock check for the last units of an item.
        DB::transaction(function () use ($type, $entry, $request, &$model, &$error): void {
            $model = $this->findEntry($type, $entry, lock: true);

            abort_unless($model->status === EntryStatus::Pending, 409);

            if ($type === 'sales') {
                $error = $this->stockOverflowError($type, (int) $model->item_id, (float) $model->quantity, (int) $model->id);
            }

            if ($error !== null) {
                return;
            }

            $model->update([
                'status' => EntryStatus::Confirmed,
                'confirmed_by' => $request->user()->id,
                'confirmed_at' => now(),
            ]);

            // Spending entries are paid from whatever the partner holds:
            // auto-deduct from their outstanding cash (may go negative —
            // the partner covered it from their own pocket and the owner
            // tops up later with a "hold").
            if (in_array($type, ['purchases', 'expenses'], true)) {
                $amount = $type === 'purchases' ? (float) $model->total : (float) $model->amount;

                if ($amount > 0) {
                    PartnerCashMovement::query()->create([
                        'user_id' => $model->user_id,
                        'type' => CashMovementType::Spent,
                        'amount' => $amount,
                        'note' => $type === 'purchases'
                            ? __('messages.auto_spent_purchase', ['id' => $model->id])
                            : __('messages.auto_spent_expense', ['id' => $model->id]),
                        'occurred_at' => $model->entry_date,
                    ]);
                }
            }

            // Sale money lands in the partner's hands: auto-add it to
            // their outstanding cash (the owner records a "return" when
            // collecting it). Owner-owned sales have no custody.
            if ($type === 'sales' && ! $model->user->isOwner()) {
                $amount = (float) $model->total;

                if ($amount > 0) {
                    PartnerCashMovement::query()->create([
                        'user_id' => $model->user_id,
                        'type' => CashMovementType::Hold,
                        'amount' => $amount,
                        'note' => __('messages.auto_held_sale', ['id' => $model->id]),
                        'occurred_at' => $model->entry_date,
                    ]);
                }
            }
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

        $redirect = back()->with('success', __('messages.entry_confirmed'));

        $warnings = [];

        // A product-cost expense without a linked product spreads across
        // the average cost of EVERY stocked item — say so right away.
        if ($type === 'expenses') {
            $head = $model->expenseHead;

            if ($head?->cost_type?->addsToStock() && $model->item_id === null) {
                $warnings[] = __('messages.product_expense_no_item_warning');
            }
        }

        // Approving a purchase/expense spends cash — warn (never block)
        // when it overdraws the balance; partners' requests stay free.
        if (in_array($type, ['purchases', 'expenses'], true)) {
            $cash = BusinessStats::all()['cash_in_hand'];
            if ($cash < 0) {
                $amount = $type === 'purchases' ? (float) $model->total : (float) $model->amount;
                $warnings[] = __('messages.cash_overdraw_warning', [
                    'cash' => number_format($cash + $amount, 2),
                    'short' => number_format($cash, 2),
                ]);
            }
        }

        if ($warnings !== []) {
            $redirect->with('warning', implode(' ', $warnings));
        }

        return $redirect;
    }

    /**
     * Reject an entry (partner made a mistake). Rejected entries stay
     * visible with a badge but never count in totals.
     */
    public function reject(string $type, int $entry, Request $request): RedirectResponse
    {
        if (! $this->cycleOpen()) {
            return back()->with('warning', __('messages.confirm_blocked_no_period'));
        }

        $model = $this->findEntry($type, $entry);

        $model->update([
            'status' => EntryStatus::Rejected,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        return back()->with('success', __('messages.entry_rejected'));
    }

    /**
     * Owner edits any entry. Totals (and sale commission) are always
     * recomputed server-side.
     */
    public function update(string $type, int $entry, Request $request): RedirectResponse
    {
        if (! $this->cycleOpen()) {
            return back()->with('warning', __('messages.confirm_blocked_no_period'));
        }

        $config = EntryTypes::config($type);
        $model = $this->findEntry($type, $entry);

        $locked = $this->closedCycleEntryError($model);

        if ($locked !== null) {
            return back()->with('error', $locked);
        }

        // Capture the pre-edit total so a confirmed entry can sync the
        // partner custody ledger afterwards (the auto movement keeps
        // its original amount — the delta is compensated). Sales and
        // purchases carry `total`; expenses carry `amount`.
        $oldTotal = $type === 'expenses' ? (float) $model->amount : (float) $model->total;

        if ($type === 'expenses') {
            $validated = $request->validate([
                'head_id' => ['required', 'exists:expense_heads,id'],
                'item_id' => ['nullable', 'exists:items,id'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'entry_date' => $this->entryDateRules(),
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            $model->update([
                'expense_head_id' => $validated['head_id'],
                'item_id' => $validated['item_id'] ?? null,
                'amount' => $validated['amount'],
                'entry_date' => $this->editedEntryMoment($model, $validated['entry_date']),
                'note' => $validated['note'] ?? null,
            ]);
        } elseif ($type === 'purchases') {
            $validated = $request->validate([
                'head_id' => ['required', 'exists:items,id'],
                'quantity' => ['required', 'numeric', 'min:0.001'],
                'unit_price' => ['required', 'numeric', 'min:0.01'],
                'entry_date' => $this->entryDateRules(),
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            $model->update([
                'item_id' => $validated['head_id'],
                'quantity' => $validated['quantity'],
                'unit_price' => $validated['unit_price'],
                'total' => round($validated['quantity'] * $validated['unit_price'], 2),
                'entry_date' => $this->editedEntryMoment($model, $validated['entry_date']),
                'note' => $validated['note'] ?? null,
            ]);
        } else {
            $validated = $request->validate([
                'head_id' => ['required', 'exists:items,id'],
                'quantity' => ['required', 'numeric', 'min:0.001'],
                'unit_price' => ['required', 'numeric', 'min:0.01'],
                'entry_date' => $this->entryDateRules(),
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            $error = $this->stockOverflowError($type, (int) $validated['head_id'], (float) $validated['quantity'], (int) $model->id);
            if ($error !== null) {
                return back()->with('error', $error);
            }

            $model->update([
                'item_id' => $validated['head_id'],
                'quantity' => $validated['quantity'],
                'unit_price' => $validated['unit_price'],
                'total' => round($validated['quantity'] * $validated['unit_price'], 2),
                'entry_date' => $this->editedEntryMoment($model, $validated['entry_date']),
                'note' => $validated['note'] ?? null,
            ]);
        }

        $this->syncCustodyOnEdit($type, $model, $oldTotal);

        return back()->with('success', __('messages.entry_updated'));
    }

    /**
     * When a CONFIRMED entry's amount changed, the custody movement
     * keeps its original amount. Record the delta as a compensating
     * movement so the partner custody ledger keeps matching the
     * business cash: spending edits work one way, sale edits the other
     * (a bigger sale = more cash in the partner's hands). Owner-owned
     * entries have no custody — skipped.
     */
    private function syncCustodyOnEdit(string $type, Model $model, float $oldTotal): void
    {
        if (! in_array($type, ['purchases', 'expenses', 'sales'], true)
            || $model->status !== EntryStatus::Confirmed
            || $model->user->isOwner()) {
            return;
        }

        $newTotal = $type === 'expenses' ? (float) $model->amount : (float) $model->total;
        $delta = round($newTotal - $oldTotal, 2);

        if (abs($delta) < 0.005) {
            return;
        }

        // Sales add to custody; spending subtracts. So for a positive
        // delta: spending → more spent, sale → more held.
        $addsToCustody = $type === 'sales' ? $delta > 0 : $delta < 0;

        PartnerCashMovement::query()->create([
            'user_id' => $model->user_id,
            'type' => $addsToCustody ? CashMovementType::Hold : CashMovementType::Spent,
            'amount' => abs($delta),
            'note' => __('messages.custody_correction_note', [
                'id' => $model->id,
                'sign' => $delta > 0 ? '+' : '−',
                'amount' => number_format(abs($delta), 2),
            ]),
            'occurred_at' => $model->entry_date,
        ]);
    }

    /**
     * Permanently delete an entry (owner-triggered, JS confirm popup).
     * Deleting is only possible while a cycle is open, so closed cycles
     * keep matching their stored profit and settlements.
     */
    public function destroy(string $type, int $entry): RedirectResponse
    {
        if (! $this->cycleOpen()) {
            return back()->with('warning', __('messages.confirm_blocked_no_period'));
        }

        $model = $this->findEntry($type, $entry);

        $locked = $this->closedCycleEntryError($model);

        if ($locked !== null) {
            return back()->with('error', $locked);
        }

        // Keep the custody ledger in sync when deleting a confirmed
        // entry — the auto movement stays in the immutable ledger, so
        // the custody side must be compensated before the delete:
        // spending money goes back to the partner, sale money is
        // removed again (the sale never happened).
        if ($model->status === EntryStatus::Confirmed && ! $model->user->isOwner()) {
            if (in_array($type, ['purchases', 'expenses'], true)) {
                $amount = $type === 'purchases' ? (float) $model->total : (float) $model->amount;

                if ($amount > 0) {
                    PartnerCashMovement::query()->create([
                        'user_id' => $model->user_id,
                        'type' => CashMovementType::Hold,
                        'amount' => $amount,
                        'note' => __('messages.custody_delete_refund', ['id' => $model->id]),
                        'occurred_at' => $model->entry_date,
                    ]);
                }
            } elseif ($type === 'sales' && (float) $model->total > 0) {
                PartnerCashMovement::query()->create([
                    'user_id' => $model->user_id,
                    'type' => CashMovementType::Spent,
                    'amount' => (float) $model->total,
                    'note' => __('messages.custody_sale_delete', ['id' => $model->id]),
                    'occurred_at' => $model->entry_date,
                ]);
            }
        }

        $model->delete();

        return back()->with('success', __('messages.entry_deleted'));
    }
}
