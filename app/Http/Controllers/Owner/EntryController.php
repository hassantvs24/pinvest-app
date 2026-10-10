<?php

namespace App\Http\Controllers\Owner;

use App\Enums\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\ExpenseHead;
use App\Models\Item;
use App\Models\User;
use App\Support\BusinessStats;
use App\Support\CommissionSettlementService;
use App\Support\EntryTypes;
use App\Support\InventoryService;
use App\Support\ItemUnits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * All entries of one type with partner + status filters.
     */
    public function index(Request $request): View
    {
        $type = $request->query('type', 'sales');
        $config = EntryTypes::config($type);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        $query = $modelClass::query()->with($config['relations'])->latest('entry_date')->latest('id');

        if ($type === 'expenses') {
            $query->with('item');
        }

        $status = $request->query('status', 'all');
        if (in_array($status, ['pending', 'confirmed', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $partnerId = (int) $request->query('partner_id', 0);
        if ($partnerId > 0) {
            $query->where('user_id', $partnerId);
        }

        // Active master items for the inline edit dropdowns.
        $items = match ($type) {
            'expenses' => ExpenseHead::query()->active()->orderBy('name')->get(),
            default => Item::query()->active()->orderBy('name')->get(),
        };

        return view('owner.entries', [
            'type' => $type,
            'config' => $config,
            'entries' => $query->paginate(15)->withQueryString(),
            'status' => $status,
            'partnerId' => $partnerId,
            'partners' => User::query()->partners()->orderBy('name')->get(),
            'items' => $items,
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
     * Entry-date rules: entries must land inside the current open cycle —
     * never inside a closed one (stored closed-cycle profit must stay
     * reproducible) and never in the future.
     *
     * @return array<int, string>
     */
    private function entryDateRules(): array
    {
        $rules = ['required', 'date', 'before_or_equal:today'];
        $openPeriod = CommissionPeriod::query()->open()->latest('id')->first();

        if ($openPeriod !== null) {
            $rules[] = 'after_or_equal:'.CommissionSettlementService::effectiveStart($openPeriod)->format('Y-m-d');
        }

        return $rules;
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
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

        $redirect = back()->with('success', __('messages.entry_confirmed'));

        // Approving a purchase/expense spends cash — warn (never block)
        // when it overdraws the balance; partners' requests stay free.
        if (in_array($type, ['purchases', 'expenses'], true)) {
            $cash = BusinessStats::all()['cash_in_hand'];
            if ($cash < 0) {
                $amount = $type === 'purchases' ? (float) $model->total : (float) $model->amount;
                $redirect->with('warning', __('messages.cash_overdraw_warning', [
                    'cash' => number_format($cash + $amount, 2),
                    'short' => number_format($cash, 2),
                ]));
            }
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
                'entry_date' => $validated['entry_date'],
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
                'entry_date' => $validated['entry_date'],
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
                'entry_date' => $validated['entry_date'],
                'note' => $validated['note'] ?? null,
            ]);
        }

        return back()->with('success', __('messages.entry_updated'));
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

        $this->findEntry($type, $entry)->delete();

        return back()->with('success', __('messages.entry_deleted'));
    }
}
