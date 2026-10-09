<?php

namespace App\Http\Controllers;

use App\EntryStatus;
use App\Models\CommissionPeriod;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Support\EntryTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Entry management (partners AND the owner). Partners create their own
 * entries as "pending"; the owner's entries are auto-confirmed since the
 * owner is the approver. Nothing can be entered while no commission
 * cycle is open.
 */
class EntryController extends Controller
{
    /**
     * The currently open commission cycle, if any.
     */
    private function openPeriod(): ?CommissionPeriod
    {
        return CommissionPeriod::query()->open()->latest('id')->first();
    }

    /**
     * List the user's own entries with a status filter tab.
     */
    public function index(string $type, Request $request): View
    {
        $config = EntryTypes::config($type);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        $query = $modelClass::query()
            ->forUser($request->user()->id)
            ->with($config['item_relation'])
            ->latest('entry_date')
            ->latest('id');

        $status = $request->query('status', 'all');
        if (in_array($status, ['pending', 'confirmed', 'rejected'], true)) {
            $query->where('status', $status);
        }

        return view('entries.index', [
            'type' => $type,
            'config' => $config,
            'entries' => $query->paginate(15)->withQueryString(),
            'status' => $status,
        ]);
    }

    /**
     * Show the entry form (dropdowns from active master data only).
     * Blocked while no cycle is open.
     */
    public function create(string $type, Request $request): View|RedirectResponse
    {
        if (! $this->openPeriod()) {
            return redirect()
                ->route('entries.index', ['type' => $type])
                ->with('warning', __('messages.entry_blocked_no_period'));
        }

        $config = EntryTypes::config($type);

        /** @var class-string<Model>|null $itemsClass */
        $itemsClass = $config['items'];

        return view('entries.create', [
            'type' => $type,
            'config' => $config,
            'items' => $itemsClass::query()->active()->orderBy('name')->get(),
            'purchaseItems' => $type === 'expenses'
                ? PurchaseItem::query()->active()->orderBy('name')->get()
                : collect(),
            'today' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Store a new entry. Partners' entries are "pending" awaiting the
     * owner; the owner's own entries are auto-confirmed. Blocked while
     * no cycle is open.
     */
    public function store(string $type, Request $request): RedirectResponse
    {
        if (! $this->openPeriod()) {
            return back()->with('warning', __('messages.entry_blocked_no_period'));
        }

        $config = EntryTypes::config($type);
        $user = $request->user();
        $status = $user->isOwner() ? EntryStatus::Confirmed : EntryStatus::Pending;
        $confirmed = $user->isOwner()
            ? ['confirmed_by' => $user->id, 'confirmed_at' => now()]
            : ['confirmed_by' => null, 'confirmed_at' => null];

        if ($type === 'expenses') {
            $validated = $request->validate([
                'head_id' => ['required', 'exists:expense_heads,id'],
                'purchase_item_id' => ['nullable', 'exists:purchase_items,id'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'entry_date' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            Expense::query()->create([
                'user_id' => $user->id,
                'expense_head_id' => $validated['head_id'],
                'purchase_item_id' => $validated['purchase_item_id'] ?? null,
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?? null,
                'entry_date' => $validated['entry_date'],
                'status' => $status,
                ...$confirmed,
            ]);
        } elseif ($type === 'purchases') {
            $validated = $request->validate([
                'head_id' => ['required', 'exists:purchase_items,id'],
                'quantity' => ['required', 'integer', 'min:1'],
                'unit_price' => ['required', 'numeric', 'min:0.01'],
                'entry_date' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            Purchase::query()->create([
                'user_id' => $user->id,
                'purchase_item_id' => $validated['head_id'],
                'quantity' => $validated['quantity'],
                'unit_price' => $validated['unit_price'],
                'total' => round($validated['quantity'] * $validated['unit_price'], 2),
                'note' => $validated['note'] ?? null,
                'entry_date' => $validated['entry_date'],
                'status' => $status,
                ...$confirmed,
            ]);
        } else {
            $validated = $request->validate([
                'head_id' => ['required', 'exists:sale_items,id'],
                'quantity' => ['required', 'integer', 'min:1'],
                'unit_price' => ['required', 'numeric', 'min:0.01'],
                'entry_date' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            Sale::query()->create([
                'user_id' => $user->id,
                'sale_item_id' => $validated['head_id'],
                'quantity' => $validated['quantity'],
                'unit_price' => $validated['unit_price'],
                'total' => round($validated['quantity'] * $validated['unit_price'], 2),
                'note' => $validated['note'] ?? null,
                'entry_date' => $validated['entry_date'],
                'status' => $status,
                ...$confirmed,
            ]);
        }

        return redirect()
            ->route('entries.index', ['type' => $type])
            ->with(
                $user->isOwner() ? 'success' : 'warning',
                $user->isOwner() ? __('messages.saved_success') : __('messages.waiting_owner'),
            );
    }
}
