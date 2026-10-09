<?php

use App\EntryStatus;
use App\Enums\ExpenseCostType;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Investment;
use App\Models\OwnerWithdrawal;
use App\Models\Payout;
use App\Models\PayoutRequest;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\RegistrationAllow;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\BusinessStats;
use App\Support\CommissionSettlementService;
use App\Support\DateFormats;
use App\Support\InventoryService;
use App\Support\ItemUnits;
use App\UserRole;
use Database\Seeders\DefaultDataSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Create a user without triggering the language picker redirect.
 */
function makeUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => UserRole::Partner,
        'preferred_language' => 'bn',
        'is_active' => true,
        'commission_rate' => 5,
    ], $attributes));
}

it('logs in with email or mobile number', function (): void {
    $user = makeUser(['email' => 'a@b.com', 'phone' => '01700000001', 'password' => 'secret123']);

    $this->post('/login', ['identifier' => 'a@b.com', 'password' => 'secret123'])
        ->assertRedirect('/dashboard');

    $this->post('/logout');

    $this->post('/login', ['identifier' => '01700000001', 'password' => 'secret123'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('renders guest pages in Bangla by default and honors the saved preference', function (): void {
    // Guests (login/register) see Bangla, not English.
    $this->get('/login')
        ->assertOk()
        ->assertSee(__('messages.login', [], 'bn'));

    // A saved English preference is honored once logged in.
    $owner = makeUser([
        'role' => UserRole::Owner,
        'email' => 'owner@x.com',
        'phone' => '01900000000',
        'preferred_language' => 'en',
    ]);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(__('messages.dashboard', [], 'en'));
});

it('rejects invalid credentials with translated message', function (): void {
    makeUser(['email' => 'a@b.com', 'phone' => '01700000002']);

    $this->from('/login')->post('/login', ['identifier' => 'a@b.com', 'password' => 'wrong'])
        ->assertSessionHasErrors(['identifier' => __('messages.invalid_credentials')]);
});

it('registers a partner and forces language selection first', function (): void {
    RegistrationAllow::factory()->create(['phone' => '01612345678']);

    $this->post('/register', [
        'name' => 'Jamal',
        'phone' => '01612345678',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertRedirect('/select-language');

    $this->get('/dashboard')->assertRedirect('/select-language');

    $this->post('/select-language', ['language' => 'en'])->assertRedirect('/dashboard');

    expect(User::where('phone', '01612345678')->sole())
        ->role->toBe(UserRole::Partner)
        ->preferred_language->toBe('en');
});

it('persists language per user and renders their locale', function (): void {
    $english = makeUser(['preferred_language' => 'en']);

    $this->actingAs($english)->get('/dashboard')->assertSee('My Sales');
});

it('blocks partners from owner routes with 403', function (): void {
    $this->actingAs(makeUser())->get('/owner/entries')->assertForbidden();
    $this->actingAs(makeUser())->get('/owner/partners')->assertForbidden();
});

it('scopes partner entries to their own user id', function (): void {
    $me = makeUser(['commission_rate' => 5]);
    $other = makeUser(['phone' => '01700000003']);
    $item = SaleItem::factory()->create();
    CommissionPeriod::factory()->open()->create();

    Sale::factory()->create(['user_id' => $other->id, 'sale_item_id' => $item->id]);

    $this->actingAs($me)->post('/entries/sales', [
        'head_id' => $item->id,
        'quantity' => 2,
        'unit_price' => 500,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertRedirect();

    expect(Sale::count())->toBe(2);

    $sale = Sale::where('user_id', $me->id)->sole();
    expect($sale->user_id)->toBe($me->id)
        ->and($sale->status)->toBe(EntryStatus::Pending)
        ->and((float) $sale->total)->toBe(1000.0);
});

it('blocks entry creation and confirmation without an open cycle', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['phone' => '01700000002']);
    $item = SaleItem::factory()->create();

    // Partner form + store are blocked.
    $this->actingAs($partner)->get('/entries/sales/create')->assertRedirect();
    $this->actingAs($partner)->post('/entries/sales', [
        'head_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 100,
        'entry_date' => now()->format('Y-m-d'),
    ]);
    expect(Sale::count())->toBe(0);

    // Owner cannot confirm without an open cycle.
    $sale = Sale::factory()->create(['sale_item_id' => $item->id, 'status' => EntryStatus::Pending]);
    $this->actingAs($owner)->patch("/owner/entries/sales/{$sale->id}/confirm");
    expect($sale->fresh()->status)->toBe(EntryStatus::Pending);

    // Opening a cycle unblocks both.
    CommissionPeriod::factory()->open()->create();
    $this->actingAs($owner)->patch("/owner/entries/sales/{$sale->id}/confirm");
    expect($sale->fresh()->status)->toBe(EntryStatus::Confirmed);
});

it('lets the owner create entries that are auto-confirmed', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $item = SaleItem::factory()->create();
    CommissionPeriod::factory()->open()->create();

    $this->actingAs($owner)->post('/entries/sales', [
        'head_id' => $item->id,
        'quantity' => 3,
        'unit_price' => 200,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertRedirect();

    $sale = Sale::sole();
    expect($sale->status)->toBe(EntryStatus::Confirmed)
        ->and($sale->confirmed_by)->toBe($owner->id)
        ->and($sale->user_id)->toBe($owner->id);
});

it('scopes the owner dashboard to the running cycle with estimated commissions', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000017']);
    CommissionPeriod::factory()->open()->create(['opened_at' => now()->subDays(3)]);
    $purchaseItem = PurchaseItem::factory()->create();
    // Link the sale item to the purchase item so COGS applies on sale.
    $item = SaleItem::factory()->create(['purchase_item_id' => $purchaseItem->id]);
    $head = ExpenseHead::factory()->create();

    // Inside the running cycle: sale 400, purchase 200, expense 50.
    Sale::factory()->create([
        'user_id' => $partner->id, 'sale_item_id' => $item->id,
        'quantity' => 1, 'unit_price' => 400, 'total' => 400,
        'entry_date' => now()->subDay(), 'status' => EntryStatus::Confirmed,
    ]);
    Purchase::factory()->create([
        'user_id' => $partner->id, 'purchase_item_id' => $purchaseItem->id,
        'quantity' => 1, 'unit_price' => 200, 'total' => 200,
        'entry_date' => now()->subDay(), 'status' => EntryStatus::Confirmed,
    ]);
    Expense::factory()->create([
        'user_id' => $partner->id, 'expense_head_id' => $head->id,
        'amount' => 50,
        'entry_date' => now()->subDay(), 'status' => EntryStatus::Confirmed,
    ]);

    // Before the cycle opened — must NOT appear.
    Sale::factory()->create([
        'user_id' => $partner->id, 'sale_item_id' => $item->id,
        'quantity' => 1, 'unit_price' => 100, 'total' => 100,
        'entry_date' => now()->subMonths(2), 'status' => EntryStatus::Confirmed,
    ]);
    // Before the cycle opened — must NOT appear (different item, so it
    // also cannot distort this cycle's weighted average cost).
    Purchase::factory()->create([
        'user_id' => $partner->id, 'purchase_item_id' => PurchaseItem::factory(),
        'quantity' => 1, 'unit_price' => 999, 'total' => 999,
        'entry_date' => now()->subMonths(2), 'status' => EntryStatus::Confirmed,
    ]);
    Expense::factory()->create([
        'user_id' => $partner->id, 'expense_head_id' => $head->id,
        'amount' => 888,
        'entry_date' => now()->subMonths(2), 'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertOk()
        // Leaderboard + cards show only the running cycle's figures.
        ->assertSee('৳400.00')
        ->assertSee('৳200.00')
        ->assertSee('৳50.00')
        ->assertDontSee('৳100.00')
        ->assertDontSee('৳888.00')
        // The pre-cycle purchase of another item is still in stock.
        ->assertSee('৳999.00')
        // Leaderboard shows the estimated commission value (profit 150 x
        // 10% = 15) and the highlighted rate.
        ->assertSee('৳15.00')
        ->assertSee('10%');
});

it('scopes the partner dashboard to the running cycle', function (): void {
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000018']);
    CommissionPeriod::factory()->open()->create(['opened_at' => now()->subDays(3)]);
    $item = SaleItem::factory()->create();

    Sale::factory()->create([
        'user_id' => $partner->id, 'sale_item_id' => $item->id,
        'quantity' => 1, 'unit_price' => 400, 'total' => 400,
        'entry_date' => now()->subDay(), 'status' => EntryStatus::Confirmed,
    ]);
    Sale::factory()->create([
        'user_id' => $partner->id, 'sale_item_id' => $item->id,
        'quantity' => 1, 'unit_price' => 100, 'total' => 100,
        'entry_date' => now()->subMonths(2), 'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs($partner)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('৳400.00')
        ->assertDontSee('৳100.00')
        ->assertSee('৳40.00')
        ->assertSee(__('messages.estimated_hint'));
});

it('shows each partner lifetime earned commission and rate on the leaderboard when no cycle is open', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000019']);
    $period = CommissionPeriod::factory()->create();

    CommissionSettlement::factory()->paid()->create([
        'user_id' => $partner->id,
        'commission_period_id' => $period->id,
        'amount' => 30,
    ]);

    $this->actingAs($owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('৳30.00')
        ->assertSee('10%');
});

it('shows the open cycle on both dashboards and the close summary before closing', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000012']);
    $item = SaleItem::factory()->create();
    $period = CommissionPeriod::factory()->open()->create(['opened_at' => now()->subDays(3)]);

    // Profit 100 → partner 10% = 10.
    Sale::factory()->create([
        'sale_item_id' => $item->id, 'total' => 100,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDay(),
    ]);

    // Both dashboards show the running cycle.
    $this->actingAs($owner)->get('/dashboard')
        ->assertOk()
        ->assertSee(__('messages.current_period'))
        ->assertSee(__('messages.add_sale'));
    $this->actingAs($partner)->get('/dashboard')
        ->assertOk()
        ->assertSee(__('messages.current_period'));

    // Close summary shows the sales/COGS/expense breakdown, profit and expected commission.
    $this->actingAs($owner)->get('/owner/commissions/close')
        ->assertOk()
        ->assertSee(__('messages.total_sales'))
        ->assertSee(__('messages.total_purchase'))
        ->assertSee(__('messages.cost_of_goods_sold'))
        ->assertSee(__('messages.general_expense'))
        ->assertSee(__('messages.stock_value'))
        ->assertSee('৳100.00')
        ->assertSee('৳10.00')
        ->assertSee(__('messages.owner_share'));

    // Confirming the close creates matching settlements.
    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->format('Y-m-d'),
    ]);
    expect($period->fresh()->status)->toBe('closed')
        ->and((float) CommissionSettlement::sole()->amount)->toBe(10.0);

    // Partner now sees the last cycle commission on the dashboard.
    $this->actingAs($partner)->get('/dashboard')
        ->assertOk()
        ->assertSee(__('messages.last_cycle_commission'));
});

it('uses the partner rate at closing time and ignores sales shares', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $a = makeUser(['commission_rate' => 10, 'phone' => '01700000013']);
    $b = makeUser(['commission_rate' => 20, 'phone' => '01700000014']);
    $item = SaleItem::factory()->create();
    CommissionPeriod::factory()->open()->create(['opened_at' => now()->subDays(5)]);

    // Partner A sells 10x more than B — must NOT affect commissions.
    Sale::factory()->create([
        'user_id' => $a->id, 'sale_item_id' => $item->id, 'total' => 900,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(2),
    ]);
    Sale::factory()->create([
        'user_id' => $b->id, 'sale_item_id' => $item->id, 'total' => 100,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(2),
    ]);

    // Rate changed AFTER the cycle opened — closing uses the new rate.
    $a->update(['commission_rate' => 15]);

    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->format('Y-m-d'),
    ]);

    // Profit = 1000. A: 15% = 150 (not 10%), B: 20% = 200. No sales-share math.
    expect((float) CommissionSettlement::where('user_id', $a->id)->sole()->amount)->toBe(150.0)
        ->and((float) CommissionSettlement::where('user_id', $b->id)->sole()->amount)->toBe(200.0);
});

it('blocks closing the cycle while unapproved entries exist and explains why', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['phone' => '01700000015']);
    CommissionPeriod::factory()->open()->create();
    $item = SaleItem::factory()->create();
    $head = ExpenseHead::factory()->create();

    Sale::factory()->create([
        'user_id' => $partner->id,
        'sale_item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 250,
        'total' => 250,
        'entry_date' => now(),
        'status' => EntryStatus::Pending,
    ]);
    Expense::factory()->create([
        'user_id' => $partner->id,
        'expense_head_id' => $head->id,
        'amount' => 75.50,
        'entry_date' => now(),
        'status' => EntryStatus::Pending,
    ]);

    // Close preview shows the blocking reason and hides the confirm button.
    $this->actingAs($owner)
        ->get('/owner/commissions/close')
        ->assertOk()
        ->assertSee(__('messages.unapproved_entries'))
        ->assertSee($partner->name)
        ->assertSee('৳250.00')
        ->assertSee('৳75.50')
        ->assertDontSee(__('messages.confirm_close'));

    // Submitting the close is refused and the cycle stays open.
    $this->actingAs($owner)
        ->post('/owner/commissions/close', ['closed_at' => now()->format('Y-m-d')])
        ->assertRedirect();

    expect(CommissionPeriod::sole()->status)->toBe('open')
        ->and(CommissionSettlement::count())->toBe(0);

    // Approving the entries unblocks the close.
    Sale::sole()->update(['status' => EntryStatus::Confirmed]);
    Expense::sole()->update(['status' => EntryStatus::Confirmed]);

    $this->actingAs($owner)
        ->get('/owner/commissions/close')
        ->assertOk()
        ->assertSee(__('messages.confirm_close'));

    $this->actingAs($owner)
        ->post('/owner/commissions/close', ['closed_at' => now()->format('Y-m-d')])
        ->assertRedirect();

    expect(CommissionPeriod::sole()->status)->toBe('closed');
});

it('shows the recorded time next to each transaction date', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['phone' => '01700000016']);
    CommissionPeriod::factory()->open()->create();
    $item = SaleItem::factory()->create();

    $sale = Sale::factory()->create([
        'user_id' => $partner->id,
        'sale_item_id' => $item->id,
        'status' => EntryStatus::Confirmed,
        'entry_date' => now(),
    ]);
    $time = $sale->created_at->format(DateFormats::TIME);

    $this->actingAs($owner)->get('/owner/entries?type=sales')->assertSee($time);
    $this->actingAs($partner)->get('/entries/sales')->assertSee($time);
});

it('shows owner reports with date filtering and guards them from partners', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 5, 'phone' => '01700000009']);
    $item = SaleItem::factory()->create();

    Sale::factory()->create([
        'user_id' => $partner->id,
        'sale_item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 100,
        'total' => 100,
        'entry_date' => now()->subMonths(8),
        'status' => EntryStatus::Confirmed,
    ]);

    Sale::factory()->create([
        'user_id' => $partner->id,
        'sale_item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 400,
        'total' => 400,
        'entry_date' => now(),
        'status' => EntryStatus::Confirmed,
    ]);

    // Period filter: only the recent sale counts.
    $this->actingAs($owner)
        ->get('/owner/reports?from='.now()->startOfMonth()->format('Y-m-d').'&to='.now()->format('Y-m-d'))
        ->assertOk()
        ->assertSee('৳400.00')
        ->assertDontSee('৳100.00');

    $this->actingAs($partner)->get('/owner/reports')->assertForbidden();

    // The monthly section was removed — reports are cycle-based only.
    $this->actingAs($owner)->get('/owner/reports')->assertDontSee('monthly_report');
});

it('scopes the owner report to a selected cycle and shows cycle meta', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 5, 'phone' => '01700000007']);
    $item = SaleItem::factory()->create();

    $period = CommissionPeriod::factory()->create([
        'label' => 'August session',
        'opened_at' => '2026-08-01',
        'closed_at' => '2026-08-31',
    ]);

    Sale::factory()->create([
        'user_id' => $partner->id,
        'sale_item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 400,
        'total' => 400,
        'entry_date' => '2026-08-15',
        'status' => EntryStatus::Confirmed,
    ]);

    Sale::factory()->create([
        'user_id' => $partner->id,
        'sale_item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 999,
        'total' => 999,
        'entry_date' => now(),
        'status' => EntryStatus::Confirmed,
    ]);

    // Cycle filter: only the in-range sale counts, meta shown, monthly hidden.
    $this->actingAs($owner)
        ->get('/owner/reports?cycle='.$period->id)
        ->assertOk()
        ->assertSee('August session')
        ->assertSee('৳400.00')
        ->assertDontSee('৳999.00')
        ->assertSee(__('messages.owner_share'))
        ->assertDontSee('monthly_report');

    // The cycle wins over conflicting from/to filters.
    $this->actingAs($owner)
        ->get('/owner/reports?cycle='.$period->id.'&from='.now()->format('Y-m-d').'&to='.now()->format('Y-m-d'))
        ->assertOk()
        ->assertSee('৳400.00')
        ->assertDontSee('৳999.00');

    // Invalid cycle id is ignored — normal all-time report, no cycle meta card.
    $this->actingAs($owner)
        ->get('/owner/reports?cycle=999')
        ->assertOk()
        ->assertDontSee(trans_choice(__('messages.period_days'), 31, ['count' => 31]))
        ->assertSee('৳999.00');
});

it('lists only the settlements of the selected cycle', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000006']);

    $periodA = CommissionPeriod::factory()->create([
        'opened_at' => '2026-08-01',
        'closed_at' => '2026-08-31',
    ]);
    $periodB = CommissionPeriod::factory()->create([
        'opened_at' => '2026-08-15',
        'closed_at' => '2026-09-15',
    ]);

    // Same period_start range, but each settlement belongs to a different cycle.
    CommissionSettlement::factory()->create([
        'user_id' => $partner->id,
        'commission_period_id' => $periodA->id,
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
        'amount' => 456.78,
    ]);
    CommissionSettlement::factory()->create([
        'user_id' => $partner->id,
        'commission_period_id' => $periodB->id,
        'period_start' => '2026-08-01',
        'period_end' => '2026-09-15',
        'amount' => 876.54,
    ]);

    $this->actingAs($owner)
        ->get('/owner/reports?cycle='.$periodA->id)
        ->assertOk()
        ->assertSee('৳456.78')
        ->assertDontSee('৳876.54');
});

it('shows commission due per partner on the report (pending settlements)', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000008']);

    CommissionSettlement::factory()->create([
        'user_id' => $partner->id,
        'amount' => 100,
        'status' => 'pending',
    ]);
    CommissionSettlement::factory()->paid()->create([
        'user_id' => $partner->id,
        'amount' => 50,
        'period_start' => now()->subMonths(2)->startOfMonth(),
        'period_end' => now()->subMonths(2)->endOfMonth(),
    ]);

    $this->actingAs($owner)->get('/owner/reports')
        ->assertOk()
        ->assertSee('৳100.00') // pending due
        ->assertSee('৳150.00'); // total earned
});

it('stores item units and rejects invalid ones', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);

    $this->actingAs($owner)->post('/owner/masters/purchase-items', [
        'name' => 'Gold',
        'unit' => 'tola',
    ])->assertRedirect();

    expect(PurchaseItem::sole()->unit)->toBe('tola');

    $this->actingAs($owner)->post('/owner/masters/purchase-items', [
        'name' => 'Milk',
        'unit' => 'litre',
    ])->assertSessionHasErrors(['unit']);

    // Unit label renders translated in entry forms.
    expect(ItemUnits::label('kg'))->toBe(__('messages.unit_kg'));
});

it('lets the owner rename master items, even when entries reference them', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $head = ExpenseHead::factory()->create(['name' => 'Transport']);

    // An entry references the item — deletion would be blocked, rename must work.
    Expense::factory()->create(['expense_head_id' => $head->id]);

    $this->actingAs($owner)->patch("/owner/masters/expense-heads/{$head->id}", [
        'name' => 'ভ্যান ভাড়া',
    ])->assertRedirect();

    expect($head->fresh()->name)->toBe('ভ্যান ভাড়া');
    $this->actingAs($owner)->get('/owner/masters')->assertSee('ভ্যান ভাড়া');
});

it('prevents duplicate master item names within a group', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    ExpenseHead::factory()->create(['name' => 'Transport']);
    $other = ExpenseHead::factory()->create(['name' => 'Rent']);

    // Adding a duplicate is rejected...
    $this->actingAs($owner)->post('/owner/masters/expense-heads', ['name' => 'Transport'])
        ->assertSessionHasErrors(['name']);

    // ...and renaming onto an existing name is rejected too.
    $this->actingAs($owner)->patch("/owner/masters/expense-heads/{$other->id}", ['name' => 'Transport'])
        ->assertSessionHasErrors(['name']);

    expect(ExpenseHead::where('name', 'Transport')->count())->toBe(1)
        ->and($other->fresh()->name)->toBe('Rent');
});

it('rejects registration when the phone is not allow-listed', function (): void {
    $this->from('/register')->post('/register', [
        'name' => 'Hacker',
        'phone' => '01600000000',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertSessionHasErrors(['phone' => __('messages.registration_not_allowed')]);

    expect(User::where('phone', '01600000000')->exists())->toBeFalse();
});

it('allows registration with an allow-listed phone exactly once', function (): void {
    RegistrationAllow::factory()->create(['phone' => '01611111111']);

    $this->post('/register', [
        'name' => 'Jamal',
        'phone' => '01611111111',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertRedirect('/select-language');

    expect(RegistrationAllow::where('phone', '01611111111')->sole()->used_at)->not->toBeNull();

    auth()->logout();

    $this->from('/register')->post('/register', [
        'name' => 'Copycat',
        'phone' => '01611111111',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertSessionHasErrors(['phone']);

    expect(User::where('phone', '01611111111')->count())->toBe(1);
});

it('computes business stats from confirmed entries and commission settlements', function (): void {
    $partner = makeUser(['commission_rate' => 5]);
    $item = SaleItem::factory()->create();
    $head = ExpenseHead::factory()->create();

    Investment::factory()->create(['amount' => 1000]);

    Sale::factory()->create([
        'user_id' => $partner->id,
        'sale_item_id' => $item->id,
        'quantity' => 2,
        'unit_price' => 500,
        'total' => 1000,
        'status' => EntryStatus::Confirmed,
    ]);

    Expense::factory()->create([
        'user_id' => $partner->id,
        'expense_head_id' => $head->id,
        'amount' => 100,
        'status' => EntryStatus::Pending, // must not count
    ]);

    // Commission totals come from settlements, not per-sale estimates.
    CommissionSettlement::factory()->create([
        'user_id' => $partner->id,
        'amount' => 50,
        'status' => 'pending',
    ]);

    $stats = BusinessStats::all();

    expect($stats['investment'])->toBe(1000.0)
        ->and($stats['sales'])->toBe(1000.0)
        ->and($stats['commission'])->toBe(50.0)
        ->and($stats['expense'])->toBe(0.0)
        ->and($stats['net_profit'])->toBe(950.0)
        // cash = investment + sales - purchase - expense - payout - withdrawal
        // (commission is NOT a cash outflow until it is paid out)
        ->and($stats['cash_in_hand'])->toBe(2000.0);
});

it('opens and closes a commission period manually, settling profit x rate', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $a = makeUser(['commission_rate' => 10, 'phone' => '01700000005']);
    $b = makeUser(['commission_rate' => 20, 'phone' => '01700000006']);
    $item = SaleItem::factory()->create();
    $openedAt = now()->subDays(10);

    // Owner opens the period with opening cash and an opening investment.
    $this->actingAs($owner)->post('/owner/commissions/open', [
        'label' => 'Test session',
        'opened_at' => $openedAt->format('Y-m-d'),
        'opening_cash' => 5000,
        'investment_amount' => 2000,
    ])->assertRedirect();

    $period = CommissionPeriod::sole();
    expect($period->status)->toBe('open')
        ->and((float) $period->opening_cash)->toBe(5000.0)
        ->and((float) Investment::sole()->amount)->toBe(2000.0);

    // A second open attempt is rejected while one period is open.
    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->format('Y-m-d'),
    ])->assertStatus(409);

    // Entries inside the period (profit = 100) and outside it (must not count).
    Sale::factory()->create([
        'user_id' => $a->id,
        'sale_item_id' => $item->id,
        'total' => 100,
        'status' => EntryStatus::Confirmed,
        'entry_date' => $openedAt->copy()->addDays(3),
    ]);
    Sale::factory()->create([
        'user_id' => $a->id,
        'sale_item_id' => $item->id,
        'total' => 999,
        'status' => EntryStatus::Confirmed,
        'entry_date' => now()->subDays(30),
    ]);

    // Owner closes the period today.
    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->format('Y-m-d'),
    ])->assertRedirect();

    expect($period->fresh()->status)->toBe('closed')
        ->and((float) $period->fresh()->profit)->toBe(100.0)
        ->and((float) CommissionSettlement::where('user_id', $a->id)->sole()->amount)->toBe(10.0) // 100 x 10%
        ->and((float) CommissionSettlement::where('user_id', $b->id)->sole()->amount)->toBe(20.0); // 100 x 20%

    // Re-closing (e.g. retry) creates nothing new — idempotent per period.
    CommissionSettlementService::closePeriod($period->fresh(), now());
    expect(CommissionSettlement::count())->toBe(2);
});

it('closes a loss period without creating commissions', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000004']);

    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->subDays(5)->format('Y-m-d'),
    ]);

    Expense::factory()->create([
        'user_id' => $partner->id,
        'expense_head_id' => ExpenseHead::factory(),
        'amount' => 500,
        'status' => EntryStatus::Confirmed,
        'entry_date' => now()->subDays(2),
    ]);

    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->format('Y-m-d'),
    ]);

    expect((float) CommissionPeriod::sole()->profit)->toBe(-500.0)
        ->and(CommissionSettlement::count())->toBe(0);
});

it('pays a partner through request and approval, reducing cash', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000007']);
    CommissionSettlement::factory()->create(['user_id' => $partner->id, 'amount' => 100]);
    Investment::factory()->create(['amount' => 1000]);

    // Cash before payment.
    expect(BusinessStats::all()['cash_in_hand'])->toBe(1000.0);

    // Partner requests payout.
    $this->actingAs($partner)->post('/my-commissions/request')->assertRedirect();
    $request = PayoutRequest::sole();
    expect($request->status)->toBe('pending');

    // Duplicate request is blocked while one is pending.
    $this->actingAs($partner)->post('/my-commissions/request');
    expect(PayoutRequest::count())->toBe(1);

    // Owner approves: settlement paid + payout created + cash reduced.
    $this->actingAs($owner)->patch("/owner/commissions/requests/{$request->id}/approve")->assertRedirect();

    expect(CommissionSettlement::sole()->status)->toBe('paid')
        ->and((float) Payout::sole()->amount)->toBe(100.0)
        ->and(PayoutRequest::sole()->status)->toBe('approved')
        ->and(BusinessStats::all()['cash_in_hand'])->toBe(900.0);
});

it('records owner profit withdrawals and reduces cash in hand', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    Investment::factory()->create(['amount' => 1000]);

    $this->actingAs($owner)->post('/owner/withdrawals', [
        'amount' => 250,
        'withdrawn_at' => now()->format('Y-m-d'),
    ])->assertRedirect();

    expect(BusinessStats::all()['cash_in_hand'])->toBe(750.0)
        ->and(BusinessStats::all()['withdrawal'])->toBe(250.0);

    $this->actingAs(makeUser())->get('/owner/withdrawals')->assertForbidden();
    $this->actingAs(makeUser())->get('/owner/commissions')->assertForbidden();
});

it('lets a user change their password from the profile', function (): void {
    $user = makeUser(['email' => 'a@b.com', 'phone' => '01700000001', 'password' => 'secret123']);

    $this->actingAs($user)->get('/profile')->assertOk()->assertSee(__('messages.change_password', [], 'bn'));

    $this->actingAs($user)->patch('/profile/password', [
        'current_password' => 'secret123',
        'password' => 'newsecret456',
        'password_confirmation' => 'newsecret456',
    ])->assertRedirect('/profile')->assertSessionHas('success');

    expect(Hash::check('newsecret456', $user->fresh()->password))->toBeTrue();
});

it('rejects a password change with a wrong current password', function (): void {
    $user = makeUser(['password' => 'secret123']);

    $this->actingAs($user)->from('/profile')->patch('/profile/password', [
        'current_password' => 'wrongpass',
        'password' => 'newsecret456',
        'password_confirmation' => 'newsecret456',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('secret123', $user->fresh()->password))->toBeTrue();
});

it('carries unsold stock across cycles so cycle profit is not distorted', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000020']);
    $purchaseItem = PurchaseItem::factory()->create();
    $item = SaleItem::factory()->create(['purchase_item_id' => $purchaseItem->id]);

    // Cycle 1: buy 10 pcs @ 100, sell nothing. Old logic would show a
    // 1000 loss here; stock must carry over instead.
    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->subDays(10)->format('Y-m-d'),
    ]);
    Purchase::factory()->create([
        'purchase_item_id' => $purchaseItem->id,
        'quantity' => 10, 'unit_price' => 100, 'total' => 1000,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(9),
    ]);
    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->subDays(8)->format('Y-m-d'),
    ]);

    $firstPeriod = CommissionPeriod::orderBy('id')->first();
    expect((float) $firstPeriod->profit)->toBe(0.0)
        ->and(CommissionSettlement::count())->toBe(0);

    // Cycle 2: sell the same 10 pcs @ 150. Profit = 1500 - 1000 = 500.
    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->subDays(5)->format('Y-m-d'),
    ]);
    Sale::factory()->create([
        'sale_item_id' => $item->id,
        'quantity' => 10, 'unit_price' => 150, 'total' => 1500,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(2),
    ]);
    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->format('Y-m-d'),
    ]);

    expect((float) CommissionPeriod::orderByDesc('id')->first()->profit)->toBe(500.0)
        ->and((float) CommissionSettlement::sole()->amount)->toBe(50.0); // 500 x 10%
});

it('adds product costs to stock and deducts general expenses from profit', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $purchaseItem = PurchaseItem::factory()->create();
    $item = SaleItem::factory()->create(['purchase_item_id' => $purchaseItem->id]);

    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->subDays(5)->format('Y-m-d'),
    ]);

    Purchase::factory()->create([
        'purchase_item_id' => $purchaseItem->id,
        'quantity' => 10, 'unit_price' => 100, 'total' => 1000,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(4),
    ]);

    // Product cost (transport) belongs to the stock of this item.
    Expense::factory()->create([
        'expense_head_id' => ExpenseHead::factory()->create(['cost_type' => ExpenseCostType::Product]),
        'purchase_item_id' => $purchaseItem->id,
        'amount' => 100,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(3),
    ]);

    // General cost hits the profit directly.
    Expense::factory()->create([
        'expense_head_id' => ExpenseHead::factory()->create(['cost_type' => ExpenseCostType::General]),
        'amount' => 50,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(3),
    ]);

    Sale::factory()->create([
        'sale_item_id' => $item->id,
        'quantity' => 10, 'unit_price' => 200, 'total' => 2000,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDay(),
    ]);

    // COGS = 10 x ((1000 + 100) / 10) = 1100; profit = 2000 - 1100 - 50.
    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->format('Y-m-d'),
    ]);

    expect((float) CommissionPeriod::sole()->profit)->toBe(850.0);
});

it('warns about unlinked sale items and counts their cost as zero', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $item = SaleItem::factory()->create(['name' => 'Gold ring']); // no purchase link

    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->subDays(3)->format('Y-m-d'),
    ]);
    Sale::factory()->create([
        'sale_item_id' => $item->id,
        'quantity' => 1, 'unit_price' => 100, 'total' => 100,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDay(),
    ]);

    $this->actingAs($owner)->get('/owner/commissions/close')
        ->assertOk()
        ->assertSee(__('messages.attention'))
        ->assertSee(__('messages.warn_unlinked_sale_items', ['items' => 'Gold ring']));

    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->format('Y-m-d'),
    ]);

    // Cost 0 → profit equals the full sale amount.
    expect((float) CommissionPeriod::sole()->profit)->toBe(100.0);
});

it('converts units when computing COGS (kg purchase, tola sale)', function (): void {
    $purchaseItem = PurchaseItem::factory()->create(['unit' => 'kg']);
    $item = SaleItem::factory()->create(['unit' => 'tola', 'purchase_item_id' => $purchaseItem->id]);

    Purchase::factory()->create([
        'purchase_item_id' => $purchaseItem->id,
        'quantity' => 1, 'unit_price' => 100000, 'total' => 100000,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(2),
    ]);
    Sale::factory()->create([
        'sale_item_id' => $item->id,
        'quantity' => 10, 'unit_price' => 15000, 'total' => 150000,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDay(),
    ]);

    // 1 kg = 1000 g at ৳100/g; 10 tola = 116.64 g → COGS = ৳11,664.
    $cogs = InventoryService::cogs(now()->subDays(3), now());

    expect($cogs)->toBe(11664.0)
        ->and(InventoryService::stockValue(now()))->toBe(round((1000 - 116.64) * 100, 2));
});

it('shows stock value on the owner dashboard and reports', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $purchaseItem = PurchaseItem::factory()->create(['name' => 'Silver bar']);

    Purchase::factory()->create([
        'purchase_item_id' => $purchaseItem->id,
        'quantity' => 5, 'unit_price' => 100, 'total' => 500,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDay(),
    ]);

    $this->actingAs($owner)->get('/dashboard')
        ->assertOk()
        ->assertSee(__('messages.stock_value'))
        ->assertSee('৳500.00');

    $this->actingAs($owner)->get('/owner/reports')
        ->assertOk()
        ->assertSee(__('messages.stock_report'))
        ->assertSee('Silver bar')
        ->assertSee('৳500.00');
});

it('tracks production from raw materials and costs the sale at the finished good average', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000021']);

    $cotton = PurchaseItem::factory()->create(['unit' => 'kg']);
    $button = PurchaseItem::factory()->create(['unit' => 'pcs']);
    $zip = PurchaseItem::factory()->create(['unit' => 'pcs']);
    $panjabi = SaleItem::factory()->create(['unit' => 'pcs']); // no purchase link

    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->subDays(6)->format('Y-m-d'),
    ]);

    // Raw materials: cotton 5kg @ 200, buttons 10 @ 5, zips 10 @ 8.
    Purchase::factory()->create(['purchase_item_id' => $cotton->id, 'quantity' => 5, 'unit_price' => 200, 'total' => 1000, 'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(5)]);
    Purchase::factory()->create(['purchase_item_id' => $button->id, 'quantity' => 10, 'unit_price' => 5, 'total' => 50, 'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(5)]);
    Purchase::factory()->create(['purchase_item_id' => $zip->id, 'quantity' => 10, 'unit_price' => 8, 'total' => 80, 'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(5)]);

    // Produce 10 panjabis: all the cotton + buttons + zips + ৳500 labour.
    $this->actingAs($owner)->post('/owner/productions', [
        'extra_cost' => 500,
        'entry_date' => now()->subDays(2)->format('Y-m-d'),
        'outputs' => [
            ['sale_item_id' => $panjabi->id, 'quantity' => 10],
        ],
        'components' => [
            ['purchase_item_id' => $cotton->id, 'quantity' => 5],
            ['purchase_item_id' => $button->id, 'quantity' => 10],
            ['purchase_item_id' => $zip->id, 'quantity' => 10],
        ],
    ])->assertRedirect()->assertSessionHas('success');

    // Materials fully consumed; finished stock: 10 @ (1630/10) = 163 each.
    $rows = InventoryService::stockRows(now());
    expect($rows['materials'])->toBe([])
        ->and($rows['finished'][0]['item']->id)->toBe($panjabi->id)
        ->and($rows['finished'][0]['base_quantity'])->toBe(10.0)
        ->and($rows['finished'][0]['avg_cost'])->toBe(163.0);

    // Sell 6 @ 300 → COGS 6 x 163 = 978; profit = 1800 - 978 = 822.
    Sale::factory()->create([
        'user_id' => $partner->id, 'sale_item_id' => $panjabi->id,
        'quantity' => 6, 'unit_price' => 300, 'total' => 1800,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDay(),
    ]);

    expect(InventoryService::cogs(now()->subDays(6), now()))->toBe(978.0)
        ->and(InventoryService::stockValue(now()))->toBe(652.0); // 4 x 163

    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->format('Y-m-d'),
    ]);

    expect((float) CommissionPeriod::sole()->profit)->toBe(822.0);
});

it('guards master items that are linked or used in production from deletion', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);

    // Purchase item linked from a sale item (no purchases yet).
    $linkedPurchase = PurchaseItem::factory()->create();
    SaleItem::factory()->create(['purchase_item_id' => $linkedPurchase->id]);

    $this->actingAs($owner)->delete("/owner/masters/purchase-items/{$linkedPurchase->id}")
        ->assertSessionHas('error');
    expect($linkedPurchase->fresh())->not->toBeNull();

    // Purchase item consumed by a production run.
    $component = PurchaseItem::factory()->create();
    $production = Production::factory()->create();
    $production->components()->create(['purchase_item_id' => $component->id, 'quantity' => 1]);
    $this->actingAs($owner)->delete("/owner/masters/purchase-items/{$component->id}")
        ->assertSessionHas('error');
    expect($component->fresh())->not->toBeNull();

    // Sale item that has a production run.
    $producedItem = SaleItem::factory()->create();
    $production = Production::factory()->create();
    $production->outputs()->create(['sale_item_id' => $producedItem->id, 'quantity' => 1]);

    $this->actingAs($owner)->delete("/owner/masters/sale-items/{$producedItem->id}")
        ->assertSessionHas('error');
    expect($producedItem->fresh())->not->toBeNull();
});

it('restricts the productions page to the owner', function (): void {
    makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['phone' => '01700000022']);

    $this->actingAs($partner)->get('/owner/productions')->assertForbidden();

    $this->actingAs(User::where('email', 'owner@x.com')->first())
        ->get('/owner/productions')
        ->assertOk()
        ->assertSee(__('messages.add_production'));
});

it('warns when more is sold than produced of a manufactured item', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $purchaseItem = PurchaseItem::factory()->create();
    $item = SaleItem::factory()->create(['name' => 'Orna']);

    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->subDays(3)->format('Y-m-d'),
    ]);
    Purchase::factory()->create([
        'purchase_item_id' => $purchaseItem->id,
        'quantity' => 10, 'unit_price' => 50, 'total' => 500,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(2),
    ]);
    $production = Production::factory()->create(['extra_cost' => 0, 'entry_date' => now()->subDay()]);
    $production->outputs()->create(['sale_item_id' => $item->id, 'quantity' => 5]);
    $production->components()->create(['purchase_item_id' => $purchaseItem->id, 'quantity' => 5]);
    Sale::factory()->create([
        'sale_item_id' => $item->id,
        'quantity' => 7, 'unit_price' => 100, 'total' => 700,
        'status' => EntryStatus::Confirmed, 'entry_date' => now(),
    ]);

    $this->actingAs($owner)->get('/owner/commissions/close')
        ->assertOk()
        ->assertSee(__('messages.warn_negative_finished_stock', ['items' => 'Orna']));
});

it('splits one purchased item into several goods and costs each sale fairly', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000023']);

    // Buy one old ornament for ৳80,000; melting costs ৳2,000 labour.
    $ornament = PurchaseItem::factory()->create(['unit' => 'pcs']);
    $gold = SaleItem::factory()->create(['unit' => 'gram', 'default_price' => 12000]);
    $scrap = SaleItem::factory()->create(['unit' => 'gram', 'default_price' => 500]);

    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->subDays(4)->format('Y-m-d'),
    ]);
    Purchase::factory()->create([
        'purchase_item_id' => $ornament->id,
        'quantity' => 1, 'unit_price' => 80000, 'total' => 80000,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(3),
    ]);

    // Melt it: 8g pure gold + 3g scrap come out.
    $this->actingAs($owner)->post('/owner/productions', [
        'extra_cost' => 2000,
        'entry_date' => now()->subDays(2)->format('Y-m-d'),
        'outputs' => [
            ['sale_item_id' => $gold->id, 'quantity' => 8],
            ['sale_item_id' => $scrap->id, 'quantity' => 3],
        ],
        'components' => [
            ['purchase_item_id' => $ornament->id, 'quantity' => 1],
        ],
    ])->assertRedirect()->assertSessionHas('success');

    // Material fully consumed; finished stock holds both outputs.
    $rows = InventoryService::stockRows(now());
    expect($rows['materials'])->toBe([])
        ->and($rows['finished'])->toHaveCount(2);

    // Sell everything: gold 8g @ 12000, scrap 3g @ 500.
    Sale::factory()->create([
        'user_id' => $partner->id, 'sale_item_id' => $gold->id,
        'quantity' => 8, 'unit_price' => 12000, 'total' => 96000,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDay(),
    ]);
    Sale::factory()->create([
        'user_id' => $partner->id, 'sale_item_id' => $scrap->id,
        'quantity' => 3, 'unit_price' => 500, 'total' => 1500,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDay(),
    ]);

    // Total cost must equal the pool (80000 + 2000), however the split
    // falls; revenue 97500 - 82000 = 15500 profit.
    expect(InventoryService::cogs(now()->subDays(4), now()))->toBe(82000.0)
        ->and(InventoryService::stockValue(now()))->toBe(0.0);

    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->format('Y-m-d'),
    ]);

    expect((float) CommissionPeriod::sole()->profit)->toBe(15500.0);
});

it('redirects guests from the root to the login page', function (): void {
    $this->get('/')->assertRedirect('/login');
});

it('keeps partner productions pending until the owner confirms them', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['phone' => '01700000030']);
    $purchaseItem = PurchaseItem::factory()->create();
    $item = SaleItem::factory()->create();

    CommissionPeriod::factory()->open()->create();
    Purchase::factory()->create([
        'purchase_item_id' => $purchaseItem->id,
        'quantity' => 10, 'unit_price' => 100, 'total' => 1000,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDay(),
    ]);

    // Partner records a production run → pending.
    $this->actingAs($partner)->post('/productions', [
        'entry_date' => now()->format('Y-m-d'),
        'outputs' => [['sale_item_id' => $item->id, 'quantity' => 5]],
        'components' => [['purchase_item_id' => $purchaseItem->id, 'quantity' => 5]],
    ])->assertRedirect()->assertSessionHas('warning');

    $production = Production::sole();
    expect($production->status)->toBe(EntryStatus::Pending);

    // Pending: no finished stock, material stock untouched.
    $rows = InventoryService::stockRows(now());
    expect($rows['finished'])->toBe([])
        ->and($rows['materials'][0]['base_quantity'] ?? 0)->toBe(10.0);

    // Owner confirms → stock moves.
    $this->actingAs($owner)->patch("/owner/productions/{$production->id}/confirm")
        ->assertRedirect();

    $rows = InventoryService::stockRows(now());
    expect($production->fresh()->status)->toBe(EntryStatus::Confirmed)
        ->and($rows['materials'][0]['base_quantity'])->toBe(5.0)
        ->and($rows['finished'][0]['base_quantity'])->toBe(5.0);
});

it('rejected productions never count in stock', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['phone' => '01700000031']);
    $purchaseItem = PurchaseItem::factory()->create();
    $item = SaleItem::factory()->create();

    CommissionPeriod::factory()->open()->create();
    Purchase::factory()->create([
        'purchase_item_id' => $purchaseItem->id,
        'quantity' => 10, 'unit_price' => 100, 'total' => 1000,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDay(),
    ]);

    $this->actingAs($partner)->post('/productions', [
        'entry_date' => now()->format('Y-m-d'),
        'outputs' => [['sale_item_id' => $item->id, 'quantity' => 5]],
        'components' => [['purchase_item_id' => $purchaseItem->id, 'quantity' => 5]],
    ]);

    $production = Production::sole();

    $this->actingAs($owner)->patch("/owner/productions/{$production->id}/reject")->assertRedirect();

    $rows = InventoryService::stockRows(now());
    expect($rows['finished'])->toBe([])
        ->and($rows['materials'][0]['base_quantity'])->toBe(10.0);
});

it('blocks closing the cycle while a production is pending', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['phone' => '01700000032']);
    $purchaseItem = PurchaseItem::factory()->create();
    $item = SaleItem::factory()->create(['name' => 'Chips Premium']);

    CommissionPeriod::factory()->open()->create();
    Sale::factory()->create([
        'sale_item_id' => $item->id, 'quantity' => 1, 'unit_price' => 100, 'total' => 100,
        'status' => EntryStatus::Confirmed, 'entry_date' => now(),
    ]);

    $this->actingAs($partner)->post('/productions', [
        'entry_date' => now()->format('Y-m-d'),
        'outputs' => [['sale_item_id' => $item->id, 'quantity' => 5]],
        'components' => [['purchase_item_id' => $purchaseItem->id, 'quantity' => 5]],
    ]);

    $this->actingAs($owner)->get('/owner/commissions/close')
        ->assertOk()
        ->assertSee(__('messages.unapproved_entries'))
        ->assertSee('Chips Premium')
        ->assertDontSee(__('messages.confirm_close'));

    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->format('Y-m-d'),
    ])->assertRedirect();

    expect(CommissionPeriod::sole()->status)->toBe('open')
        ->and(CommissionSettlement::count())->toBe(0);
});

it('counts opening cash and production labour in cash in hand', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $purchaseItem = PurchaseItem::factory()->create();
    $item = SaleItem::factory()->create();

    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->subDays(3)->format('Y-m-d'),
        'opening_cash' => 5000,
    ]);
    Purchase::factory()->create([
        'purchase_item_id' => $purchaseItem->id,
        'quantity' => 10, 'unit_price' => 100, 'total' => 1000,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(2),
    ]);

    $production = Production::factory()->create([
        'user_id' => $owner->id, 'extra_cost' => 300,
        'status' => EntryStatus::Confirmed,
        'entry_date' => now()->subDay(),
    ]);
    $production->outputs()->create(['sale_item_id' => $item->id, 'quantity' => 5]);
    $production->components()->create(['purchase_item_id' => $purchaseItem->id, 'quantity' => 5]);

    // cash = opening 5000 - purchase 1000 - labour 300
    expect(BusinessStats::all()['cash_in_hand'])->toBe(3700.0)
        ->and(BusinessStats::all()['opening_cash'])->toBe(5000.0);
});

it('rejects opening a cycle with a future date', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);

    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->addDays(2)->format('Y-m-d'),
    ])->assertSessionHasErrors('opened_at');

    expect(CommissionPeriod::count())->toBe(0);
});

it('rejects withdrawing more than the cash in hand', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    Investment::factory()->create(['amount' => 1000, 'invested_at' => now()]);

    $this->actingAs($owner)->post('/owner/withdrawals', [
        'amount' => 1500,
        'withdrawn_at' => now()->format('Y-m-d'),
    ])->assertSessionHasErrors('amount');

    $this->actingAs($owner)->post('/owner/withdrawals', [
        'amount' => 800,
        'withdrawn_at' => now()->format('Y-m-d'),
    ])->assertRedirect();

    expect(OwnerWithdrawal::count())->toBe(1)
        ->and(BusinessStats::all()['cash_in_hand'])->toBe(200.0);
});

it('lets the partner see and use the productions page', function (): void {
    $partner = makeUser(['phone' => '01700000033']);

    $this->actingAs($partner)->get('/productions')
        ->assertOk()
        ->assertSee(__('messages.add_production'));
});

it('runs the full business lifecycle across cycles with payout', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['commission_rate' => 10, 'phone' => '01700000034']);
    $wood = PurchaseItem::factory()->create(['unit' => 'pcs']);
    $chips = SaleItem::factory()->create(['unit' => 'pcs', 'purchase_item_id' => $wood->id]);
    $generalHead = ExpenseHead::factory()->create(['cost_type' => ExpenseCostType::General]);

    // Cycle 1: invest, buy 10 wood, sell 5 as chips.
    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->subDays(20)->format('Y-m-d'),
    ]);
    Investment::factory()->create(['amount' => 5000, 'invested_at' => now()->subDays(20)]);
    Purchase::factory()->create([
        'purchase_item_id' => $wood->id, 'quantity' => 10, 'unit_price' => 100, 'total' => 1000,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(15),
    ]);
    Sale::factory()->create([
        'user_id' => $partner->id, 'sale_item_id' => $chips->id,
        'quantity' => 5, 'unit_price' => 300, 'total' => 1500,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(12),
    ]);
    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->subDays(11)->format('Y-m-d'),
    ]);

    // Profit 1 = 1500 - 500 (COGS) = 1000 → settlement 100.
    expect((float) CommissionPeriod::orderBy('id')->first()->profit)->toBe(1000.0)
        ->and((float) CommissionSettlement::orderBy('id')->first()->amount)->toBe(100.0);

    // Cycle 2: re-invest mid-cycle, sell the leftover 5 wood, one general expense.
    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->subDays(10)->format('Y-m-d'),
    ]);
    Investment::factory()->create(['amount' => 2000, 'invested_at' => now()->subDays(8)]);
    Sale::factory()->create([
        'user_id' => $partner->id, 'sale_item_id' => $chips->id,
        'quantity' => 5, 'unit_price' => 320, 'total' => 1600,
        'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(5),
    ]);
    Expense::factory()->create([
        'user_id' => $partner->id, 'expense_head_id' => $generalHead->id,
        'amount' => 100, 'status' => EntryStatus::Confirmed, 'entry_date' => now()->subDays(4),
    ]);
    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->format('Y-m-d'),
    ]);

    // Profit 2 = 1600 - 500 (COGS, carried stock) - 100 = 1000 → settlement 100.
    expect((float) CommissionPeriod::orderByDesc('id')->first()->profit)->toBe(1000.0);

    // Partner requests payout of the full pending due (200).
    expect(CommissionSettlementService::pendingDue($partner->id))->toBe(200.0);
    $this->actingAs($partner)->post('/my-commissions/request')->assertRedirect();
    $requestId = PayoutRequest::sole()->id;

    $this->actingAs($owner)->patch("/owner/commissions/requests/{$requestId}/approve")->assertRedirect();

    expect(CommissionSettlement::where('status', 'paid')->count())->toBe(2)
        ->and((float) Payout::sole()->amount)->toBe(200.0);

    // Cash: 5000 + 2000 invested + 3100 sales - 1000 purchases
    //       - 100 expense - 200 payout.
    expect(BusinessStats::all()['cash_in_hand'])->toBe(8800.0);
});

it('seeds a consistent demo state with an open cycle owning the pending entries', function (): void {
    $this->seed();

    expect(CommissionPeriod::query()->open()->count())->toBe(1)
        ->and(Expense::query()->pending()->count())->toBe(1)
        ->and(Purchase::query()->pending()->count())->toBe(1);
});

it('shows the add-cash link and hint on the owner dashboard cash card', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);

    $this->actingAs($owner)->get('/dashboard')
        ->assertOk()
        ->assertSee(__('messages.add_cash'))
        ->assertSee(__('messages.cash_pool_hint'))
        ->assertSee(route('owner.investments.index'));
});

it('seeds permanent Bangla defaults with accounts, masters and links', function (): void {
    $this->seed(DefaultDataSeeder::class);

    // Owner + the three real partners.
    expect(User::query()->where('phone', '01675870047')->where('role', UserRole::Owner)->exists())->toBeTrue()
        ->and(User::query()->where('phone', '01747666533')->exists())->toBeTrue()
        ->and(User::query()->where('phone', '01705752545')->exists())->toBeTrue()
        ->and(User::query()->where('phone', '01641196743')->exists())->toBeTrue();

    // Bangla masters.
    expect(ExpenseHead::query()->count())->toBeGreaterThanOrEqual(8)
        ->and(PurchaseItem::query()->where('name', 'অগর গাছ')->exists())->toBeTrue()
        ->and(SaleItem::query()->where('name', 'উদ চিপস — প্রিমিয়াম')->exists())->toBeTrue();

    // Oil sale items are linked for direct resale.
    $gradeA = SaleItem::query()->where('name', 'উদ অয়েল — গ্রেড A')->first();
    expect($gradeA->purchaseItem?->name)->toBe('উদ অয়েল');

    // Re-running the default seeder creates no duplicates.
    $heads = ExpenseHead::query()->count();
    $this->seed(DefaultDataSeeder::class);
    expect(ExpenseHead::query()->count())->toBe($heads)
        ->and(User::query()->count())->toBe(4);
});

it('only seeds demo data on a database without any commission period', function (): void {
    $this->seed(DefaultDataSeeder::class);

    // Empty of cycles → demo runs.
    $this->seed(DemoDataSeeder::class);
    expect(CommissionPeriod::query()->count())->toBe(11); // 10 closed + 1 demo open

    // Cycles now exist → demo is skipped, no duplicates.
    $this->seed(DemoDataSeeder::class);
    expect(CommissionPeriod::query()->count())->toBe(11);
});

it('serves the app with a local icon, local jQuery and per-request styling', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);

    $response = $this->actingAs($owner)->get('/dashboard');

    $response->assertOk()
        ->assertSee('icon.svg', escape: false)
        ->assertDontSee('code.jquery.com', escape: false)
        ->assertSee('js/jquery.min.js', escape: false);

    expect(file_exists(public_path('icon.svg')))->toBeTrue()
        ->and(file_exists(public_path('js/jquery.min.js')))->toBeTrue();
});

it('renders per-user avatars with distinct colors and name initials', function (): void {
    $a = makeUser(['name' => 'রাজু', 'phone' => '01700000040']);
    $b = makeUser(['name' => 'Sahel', 'phone' => '01700000041']);

    $htmlA = view('components.avatar', ['user' => $a, 'size' => 32])->render();
    $htmlB = view('components.avatar', ['user' => $b, 'size' => 32])->render();

    expect($htmlA)->toContain('>র</text>')
        ->and($htmlB)->toContain('>S</text>')
        ->and($htmlA)->not->toBe($htmlB); // different id → different color
});

it('shows entry form hints and cautions in the user language', function (): void {
    $partner = makeUser(['phone' => '01700000050']); // bn locale
    CommissionPeriod::factory()->open()->create();

    $this->actingAs($partner)->get('/entries/sales/create')
        ->assertOk()
        ->assertSee(__('messages.entry_hint_sales', [], 'bn'), escape: false)
        ->assertSee(__('messages.warn_sale_unlinked', [], 'bn'), escape: false);

    $this->actingAs($partner)->get('/entries/purchases/create')
        ->assertOk()
        ->assertSee(__('messages.entry_hint_purchases', [], 'bn'), escape: false);

    // English locale shows the English versions of the same keys.
    $partner->update(['preferred_language' => 'en']);
    $this->actingAs($partner)->get('/entries/sales/create')
        ->assertSee(__('messages.entry_hint_sales', [], 'en'), escape: false);
});

it('shows section hints across owner and partner pages', function (): void {
    $owner = makeUser(['role' => UserRole::Owner, 'email' => 'owner@x.com', 'phone' => '01900000000']);
    $partner = makeUser(['phone' => '01700000051']);

    $this->actingAs($owner)->get('/owner/masters')
        ->assertSee(__('messages.masters_hint_expense_heads'), escape: false)
        ->assertSee(__('messages.masters_hint_sale_items'), escape: false)
        ->assertSee(__('messages.warn_unit_locked'), escape: false);

    $this->actingAs($owner)->get('/owner/entries')
        ->assertSee(__('messages.owner_entries_hint'), escape: false);

    $this->actingAs($owner)->get('/owner/partners/create')
        ->assertSee(__('messages.partner_rate_hint'), escape: false);

    $this->actingAs($owner)->get('/owner/commissions')
        ->assertSee(__('messages.payout_requests_hint'), escape: false);

    $this->actingAs($owner)->get('/owner/reports')
        ->assertSee(__('messages.reports_intro_hint'), escape: false);

    $this->actingAs($partner)->get('/my-commissions')
        ->assertSee(__('messages.my_commissions_hint'), escape: false);

    $this->actingAs($partner)->get('/productions')
        ->assertSee(__('messages.production_pending_hint'), escape: false);
});
