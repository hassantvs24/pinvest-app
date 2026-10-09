<?php

use App\EntryStatus;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Investment;
use App\Models\Payout;
use App\Models\PayoutRequest;
use App\Models\RegistrationAllow;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\BusinessStats;
use App\Support\CommissionSettlementService;
use App\UserRole;
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
        ->and((float) $sale->total)->toBe(1000.0)
        ->and((float) $sale->commission_amount)->toBe(50.0);
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
        'commission_rate' => 5,
        'commission_amount' => 5,
        'entry_date' => now()->subMonths(8),
        'status' => EntryStatus::Confirmed,
    ]);

    Sale::factory()->create([
        'user_id' => $partner->id,
        'sale_item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 400,
        'total' => 400,
        'commission_rate' => 5,
        'commission_amount' => 20,
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
        'commission_rate' => 5,
        'commission_amount' => 50,
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
