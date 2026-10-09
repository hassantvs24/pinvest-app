<?php

namespace App\Http\Controllers\Owner;

use App\EntryStatus;
use App\Http\Controllers\Controller;
use App\Models\CommissionPeriod;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Owner-only: view all partners' entries, confirm/reject, edit, hard delete.
 */
class EntryController extends Controller
{
    /**
     * Allowed entry types => model + eager-load relations.
     *
     * @return array<string, array{model: class-string<Model>, relations: array<int, string>, item_field: string, head_label: string}>
     */
    private function types(): array
    {
        return [
            'expenses' => [
                'model' => Expense::class,
                'relations' => ['user', 'expenseHead'],
                'item_field' => 'expense_head_id',
                'head_label' => 'expense_head',
            ],
            'purchases' => [
                'model' => Purchase::class,
                'relations' => ['user', 'purchaseItem'],
                'item_field' => 'purchase_item_id',
                'head_label' => 'purchase_item',
            ],
            'sales' => [
                'model' => Sale::class,
                'relations' => ['user', 'saleItem'],
                'item_field' => 'sale_item_id',
                'head_label' => 'sale_item',
            ],
        ];
    }

    /**
     * @return array{model: class-string<Model>, relations: array<int, string>, item_field: string, head_label: string}
     */
    private function typeConfig(string $type): array
    {
        $types = $this->types();
        abort_unless(isset($types[$type]), 404);

        return $types[$type];
    }

    /**
     * Find an entry of the given type or 404.
     */
    private function findEntry(string $type, int $id): Model
    {
        $config = $this->typeConfig($type);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        return $modelClass::query()->findOrFail($id);
    }

    /**
     * All entries of one type with partner + status filters.
     */
    public function index(Request $request): View
    {
        $type = $request->query('type', 'sales');
        $config = $this->typeConfig($type);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        $query = $modelClass::query()->with($config['relations'])->latest('entry_date')->latest('id');

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
            'purchases' => PurchaseItem::query()->active()->orderBy('name')->get(),
            default => SaleItem::query()->active()->orderBy('name')->get(),
        };

        return view('owner.entries', [
            'type' => $type,
            'config' => $config,
            'entries' => $query->paginate(15)->withQueryString(),
            'status' => $status,
            'partnerId' => $partnerId,
            'partners' => User::query()->partners()->orderBy('name')->get(),
            'items' => $items,
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
     * Confirm a pending entry.
     */
    public function confirm(string $type, int $entry, Request $request): RedirectResponse
    {
        if (! $this->cycleOpen()) {
            return back()->with('warning', __('messages.confirm_blocked_no_period'));
        }

        $model = $this->findEntry($type, $entry);

        $model->update([
            'status' => EntryStatus::Confirmed,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        return back()->with('success', __('messages.entry_confirmed'));
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

        $config = $this->typeConfig($type);
        $model = $this->findEntry($type, $entry);

        if ($type === 'expenses') {
            $validated = $request->validate([
                'head_id' => ['required', 'exists:expense_heads,id'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'entry_date' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            $model->update([
                'expense_head_id' => $validated['head_id'],
                'amount' => $validated['amount'],
                'entry_date' => $validated['entry_date'],
                'note' => $validated['note'] ?? null,
            ]);
        } elseif ($type === 'purchases') {
            $validated = $request->validate([
                'head_id' => ['required', 'exists:purchase_items,id'],
                'quantity' => ['required', 'integer', 'min:1'],
                'unit_price' => ['required', 'numeric', 'min:0.01'],
                'entry_date' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            $model->update([
                'purchase_item_id' => $validated['head_id'],
                'quantity' => $validated['quantity'],
                'unit_price' => $validated['unit_price'],
                'total' => round($validated['quantity'] * $validated['unit_price'], 2),
                'entry_date' => $validated['entry_date'],
                'note' => $validated['note'] ?? null,
            ]);
        } else {
            $validated = $request->validate([
                'head_id' => ['required', 'exists:sale_items,id'],
                'quantity' => ['required', 'integer', 'min:1'],
                'unit_price' => ['required', 'numeric', 'min:0.01'],
                'entry_date' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            $model->update([
                'sale_item_id' => $validated['head_id'],
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
     */
    public function destroy(string $type, int $entry): RedirectResponse
    {
        $this->findEntry($type, $entry)->delete();

        return back()->with('success', __('messages.entry_deleted'));
    }
}
