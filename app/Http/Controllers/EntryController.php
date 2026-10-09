<?php

namespace App\Http\Controllers;

use App\EntryStatus;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Partner-side entry management. Partners can only create and view
 * their OWN entries — never edit or delete.
 */
class EntryController extends Controller
{
    /**
     * Allowed entry types => configuration.
     *
     * @return array<string, array{model: class-string<Model>, items: class-string<Model>|null, item_relation: string|null, label: string, route: string, icon: string, color: string, has_quantity: bool, has_unit_price: bool, has_head: bool}
     */
    private function types(): array
    {
        return [
            'expenses' => [
                'model' => Expense::class,
                'items' => ExpenseHead::class,
                'item_relation' => 'expenseHead',
                'label' => 'expenses',
                
                'icon' => '💸',
                'color' => 'rose',
                'has_quantity' => false,
                'has_unit_price' => false,
                'has_head' => true,
            ],
            'purchases' => [
                'model' => Purchase::class,
                'items' => PurchaseItem::class,
                'item_relation' => 'purchaseItem',
                'label' => 'purchases',
                
                'icon' => '🛒',
                'color' => 'orange',
                'has_quantity' => true,
                'has_unit_price' => true,
                'has_head' => false,
            ],
            'sales' => [
                'model' => Sale::class,
                'items' => SaleItem::class,
                'item_relation' => 'saleItem',
                'label' => 'sales',
                
                'icon' => '💰',
                'color' => 'blue',
                'has_quantity' => true,
                'has_unit_price' => true,
                'has_head' => false,
            ],
        ];
    }

    /**
     * Resolve a type key into its config or abort 404.
     *
     * @return array{model: class-string<Model>, items: class-string<Model>|null, item_relation: string|null, label: string, route: string, icon: string, color: string, has_quantity: bool, has_unit_price: bool, has_head: bool}
     */
    private function typeConfig(string $type): array
    {
        abort_unless(isset($this->types()[$type]), 404);

        return $this->types()[$type];
    }

    /**
     * List the partner's own entries with a status filter tab.
     */
    public function index(string $type, Request $request): View
    {
        $config = $this->typeConfig($type);

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
     */
    public function create(string $type, Request $request): View
    {
        $config = $this->typeConfig($type);

        /** @var class-string<Model>|null $itemsClass */
        $itemsClass = $config['items'];

        return view('entries.create', [
            'type' => $type,
            'config' => $config,
            'items' => $itemsClass::query()->active()->orderBy('name')->get(),
            'commissionRate' => (float) $request->user()->commission_rate,
            'today' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Store a new entry as "pending". Commission is always calculated
     * server-side from the partner's saved rate.
     */
    public function store(string $type, Request $request): RedirectResponse
    {
        $config = $this->typeConfig($type);
        $user = $request->user();

        if ($type === 'expenses') {
            $validated = $request->validate([
                'head_id' => ['required', 'exists:expense_heads,id'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'entry_date' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            Expense::query()->create([
                'user_id' => $user->id,
                'expense_head_id' => $validated['head_id'],
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?? null,
                'entry_date' => $validated['entry_date'],
                'status' => EntryStatus::Pending,
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
                'status' => EntryStatus::Pending,
            ]);
        } else {
            $validated = $request->validate([
                'head_id' => ['required', 'exists:sale_items,id'],
                'quantity' => ['required', 'integer', 'min:1'],
                'unit_price' => ['required', 'numeric', 'min:0.01'],
                'entry_date' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:1000'],
            ]);

            $total = round($validated['quantity'] * $validated['unit_price'], 2);
            $commissionRate = (float) $user->commission_rate;

            Sale::query()->create([
                'user_id' => $user->id,
                'sale_item_id' => $validated['head_id'],
                'quantity' => $validated['quantity'],
                'unit_price' => $validated['unit_price'],
                'total' => $total,
                'commission_rate' => $commissionRate,
                'commission_amount' => round($total * $commissionRate / 100, 2),
                'note' => $validated['note'] ?? null,
                'entry_date' => $validated['entry_date'],
                'status' => EntryStatus::Pending,
            ]);
        }

        return redirect()
            ->route('entries.index', ['type' => $type])
            ->with('warning', __('messages.waiting_owner'));
    }
}
