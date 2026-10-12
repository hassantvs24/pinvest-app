<?php

use App\Enums\CashMovementType;
use App\Enums\EntryStatus;
use App\Enums\UserRole;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Investment;
use App\Models\Item;
use App\Models\PartnerCashMovement;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Support\BusinessStats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cashOwner(): User
{
    return User::factory()->create([
        'role' => UserRole::Owner,
        'preferred_language' => 'bn',
    ]);
}

function cashPartner(): User
{
    return User::factory()->create(['preferred_language' => 'bn']);
}

it('shows the partner cash page to the owner but blocks partners', function (): void {
    $partner = cashPartner();

    $this->actingAs(cashOwner())->get('/owner/partner-cash')
        ->assertOk()
        ->assertSee(__('messages.partner_cash_tab_hold'))
        ->assertSee(__('messages.partner_cash_tab_return'))
        ->assertSee(__('messages.partner_cash_tab_spent'));

    $this->actingAs($partner)->get('/owner/partner-cash')->assertForbidden();
});

it('records a hold: cash with partners rises, cash in hand is untouched', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    Investment::factory()->create(['amount' => 100000]);

    $before = BusinessStats::all();

    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 20000,
        'occurred_at' => now()->toDateString(),
        'note' => 'বিক্রির টাকা',
    ])->assertRedirect();

    $after = BusinessStats::all();

    expect($after['cash_with_partners'])->toBe(20000.0)
        ->and($after['cash_in_hand'])->toBe($before['cash_in_hand']);
});

it('rejects a hold for a non-partner user', function (): void {
    $owner = User::factory()->owner()->create();
    $notPartner = User::factory()->owner()->create();

    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $notPartner->id,
        'amount' => 1000,
        'occurred_at' => now()->toDateString(),
    ])->assertSessionHasErrors('user_id');
});

it('records a return and spends down the outstanding', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partner->id, 'amount' => 10000]);

    $this->actingAs($owner)->post('/owner/partner-cash/return', [
        'user_id' => $partner->id,
        'amount' => 4000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    expect(PartnerCashMovement::outstanding($partner->id))->toBe(6000.0);
});

it('records a cash adjustment in both directions and allows a negative balance', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partner->id, 'amount' => 5000]);

    // Decrease beyond the held cash: the balance goes negative — the
    // partner covered it from their own pocket.
    $this->actingAs($owner)->post('/owner/partner-cash/cash-adjust', [
        'user_id' => $partner->id,
        'direction' => 'subtract',
        'amount' => 8000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    expect(PartnerCashMovement::outstanding($partner->id))->toBe(-3000.0);

    // Increase brings it back — the owner tops up with an adjustment.
    $this->actingAs($owner)->post('/owner/partner-cash/cash-adjust', [
        'user_id' => $partner->id,
        'direction' => 'add',
        'amount' => 3000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    expect(PartnerCashMovement::outstanding($partner->id))->toBe(0.0);

    // An adjustment without a note gets a default marker note.
    expect(PartnerCashMovement::query()->where('user_id', $partner->id)->latest('id')->value('note'))
        ->toContain('ক্যাশ অ্যাডজাস্টমেন্ট');
});

it('a return still cannot exceed what the partner holds', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partner->id, 'amount' => 5000]);

    $this->actingAs($owner)->post('/owner/partner-cash/return', [
        'user_id' => $partner->id,
        'amount' => 6000,
        'occurred_at' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    expect(PartnerCashMovement::outstanding($partner->id))->toBe(5000.0);
});

it('full scenario: investment -> hold -> partner purchase auto-deducts the held cash', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    CommissionPeriod::factory()->open()->create();
    Investment::factory()->create(['amount' => 100000]);

    // Owner hands the investment money to the partner.
    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 100000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    // Partner buys stock with it; approving the entry spends business
    // cash AND auto-deducts the partner's held cash in one step.
    $purchase = Purchase::factory()->create([
        'user_id' => $partner->id,
        'total' => 40000,
        'entry_date' => now(),
    ]);
    $this->actingAs($owner)->patch("/owner/entries/purchases/{$purchase->id}/confirm")->assertRedirect();

    expect(BusinessStats::all()['cash_in_hand'])->toBe(60000.0)
        ->and(PartnerCashMovement::outstanding($partner->id))->toBe(60000.0);

    // A second purchase beyond the held cash drives it negative — the
    // owner tops up with a hold to settle.
    $overspend = Purchase::factory()->create([
        'user_id' => $partner->id,
        'total' => 80000,
        'entry_date' => now(),
    ]);
    $this->actingAs($owner)->patch("/owner/entries/purchases/{$overspend->id}/confirm")->assertRedirect();

    expect(PartnerCashMovement::outstanding($partner->id))->toBe(-20000.0);

    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 20000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    expect(PartnerCashMovement::outstanding($partner->id))->toBe(0.0);
});

it('settling commission from held cash marks the settlement paid without a payout', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    $settlement = CommissionSettlement::factory()->create([
        'user_id' => $partner->id,
        'amount' => 3000,
        'status' => 'pending',
    ]);
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partner->id, 'amount' => 5000]);

    $cashBefore = BusinessStats::all()['cash_in_hand'];

    $this->actingAs($owner)->post('/owner/partner-cash/adjust', [
        'commission_settlement_id' => $settlement->id,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    expect($settlement->refresh()->status)->toBe('paid')
        ->and(PartnerCashMovement::outstanding($partner->id))->toBe(2000.0)
        ->and(BusinessStats::all()['cash_in_hand'])->toBe($cashBefore)
        ->and(PartnerCashMovement::query()
            ->where('type', CashMovementType::CommissionAdjustment)
            ->where('commission_settlement_id', $settlement->id)
            ->count())->toBe(1);
});

it('refuses adjustments beyond the held cash or for non-pending settlements', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    $otherPartner = cashPartner();
    $settlement = CommissionSettlement::factory()->create([
        'user_id' => $partner->id,
        'amount' => 3000,
        'status' => 'pending',
    ]);
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partner->id, 'amount' => 2000]);

    // Held cash (2000) < settlement (3000).
    $this->actingAs($owner)->post('/owner/partner-cash/adjust', [
        'commission_settlement_id' => $settlement->id,
        'occurred_at' => now()->toDateString(),
    ])->assertSessionHasErrors('commission_settlement_id');
    expect($settlement->refresh()->status)->toBe('pending');

    // Settlement of another partner is untouched by this partner's holds,
    // and a paid settlement cannot be adjusted again.
    $paidSettlement = CommissionSettlement::factory()->paid()->create([
        'user_id' => $otherPartner->id,
        'amount' => 1000,
    ]);
    $this->actingAs($owner)->post('/owner/partner-cash/adjust', [
        'commission_settlement_id' => $paidSettlement->id,
        'occurred_at' => now()->toDateString(),
    ])->assertStatus(409);
});

it('shows the held cash figure on the partner dashboard', function (): void {
    $partner = cashPartner();
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partner->id, 'amount' => 7000]);

    $this->actingAs($partner)->get('/dashboard')
        ->assertOk()
        ->assertSee(__('messages.my_held_cash'))
        ->assertSee('৳7,000.00');
});

it('breaks down cash in hand on the owner dashboard', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    Investment::factory()->create(['amount' => 100000]);
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partner->id, 'amount' => 30000]);

    $this->actingAs($owner)->get('/dashboard')
        ->assertOk()
        ->assertSee('৳100,000.00') // total cash in hand
        ->assertSee('৳70,000.00') // in own custody (100k - 30k)
        ->assertSee('৳30,000.00'); // with partners
});

it('shows a zero partner figure on the owner dashboard without holds', function (): void {
    $this->actingAs(cashOwner())->get('/dashboard')
        ->assertOk()
        ->assertSee('৳0.00');
});

it('shows partner cash settlement review on the cycle close summary', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    CommissionPeriod::factory()->open()->create();
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partner->id, 'amount' => 10000]);

    $this->actingAs($owner)->get('/owner/commissions/close')
        ->assertOk()
        ->assertSee(__('messages.close_partner_cash_title'))
        ->assertSee($partner->name)
        ->assertSee('৳10,000.00')
        ->assertDontSee(__('messages.close_partner_cash_negative_hint'));
});

it('warns on the close summary when a partner balance is negative', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    CommissionPeriod::factory()->open()->create();
    PartnerCashMovement::factory()->spent()->create(['user_id' => $partner->id, 'amount' => 5000]);

    $this->actingAs($owner)->get('/owner/commissions/close')
        ->assertOk()
        ->assertSee(__('messages.close_partner_cash_negative_hint'));
});

it('stores occurred_at as an exact moment following the entry convention', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();

    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 1000,
        'occurred_at' => now()->toDateString(),
    ]);

    $today = PartnerCashMovement::query()->latest('id')->firstOrFail();
    expect($today->occurred_at->isSameDay(now()))->toBeTrue()
        ->and($today->occurred_at->gt($today->occurred_at->copy()->startOfDay()))->toBeTrue();

    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 1000,
        'occurred_at' => now()->subDays(3)->toDateString(),
    ]);

    $past = PartnerCashMovement::query()->latest('id')->firstOrFail();
    expect($past->occurred_at->toDateTimeString())->toBe(now()->subDays(3)->startOfDay()->toDateTimeString());
});

it('scopes cash with partners to exact cycle boundaries', function (): void {
    $partner = cashPartner();
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partner->id, 'amount' => 1000, 'occurred_at' => now()->subDays(5)]);
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partner->id, 'amount' => 2000, 'occurred_at' => now()]);

    $scoped = BusinessStats::all(null, now()->subDay(), now());

    expect($scoped['cash_with_partners'])->toBe(2000.0)
        ->and(BusinessStats::all()['cash_with_partners'])->toBe(3000.0);
});

it('auto-deducts production labour from the partner held cash on confirm', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    CommissionPeriod::factory()->open()->create();
    Investment::factory()->create(['amount' => 100000]);

    // Partner holds 20k and buys raw material (10k) for the run.
    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 20000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    $wood = Item::factory()->create();
    $purchase = Purchase::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $wood->id,
        'quantity' => 10,
        'unit_price' => 1000,
        'total' => 10000,
        'entry_date' => now(),
    ]);
    $this->actingAs($owner)->patch("/owner/entries/purchases/{$purchase->id}/confirm")->assertRedirect();

    // Partner runs production with 3k cash labour (মজুরি). The output
    // item's price is fixed so the run always passes the value check.
    $chips = Item::factory()->create(['default_price' => 5000]);
    $production = Production::factory()->create([
        'user_id' => $partner->id,
        'extra_cost' => 3000,
        'entry_date' => now(),
        'status' => EntryStatus::Pending,
    ]);
    $production->components()->create(['item_id' => $wood->id, 'quantity' => 5]);
    $production->outputs()->create(['item_id' => $chips->id, 'quantity' => 5]);
    $this->actingAs($owner)->patch("/owner/productions/{$production->id}/confirm")->assertRedirect();

    // Business cash drops by material + labour; the partner's held
    // cash drops by the same two auto-spent movements.
    expect(BusinessStats::all()['cash_in_hand'])->toBe(87000.0)
        ->and(PartnerCashMovement::outstanding($partner->id))->toBe(7000.0)
        ->and(PartnerCashMovement::query()
            ->where('type', CashMovementType::Spent)
            ->where('user_id', $partner->id)
            ->count())->toBe(2);
});

it('owner production runs never touch partner cash', function (): void {
    $owner = cashOwner();
    CommissionPeriod::factory()->open()->create();
    $chips = Item::factory()->create();

    $this->actingAs($owner)->post('/owner/productions', [
        'extra_cost' => 2000,
        'entry_date' => now()->toDateString(),
        'outputs' => [['item_id' => $chips->id, 'quantity' => 5]],
        'components' => [],
    ])->assertRedirect();

    expect(PartnerCashMovement::query()->count())->toBe(0);
});

it('refunds the custody difference when a confirmed purchase is edited down', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    CommissionPeriod::factory()->open()->create();
    Investment::factory()->create(['amount' => 100000]);

    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 20000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    $wood = Item::factory()->create();
    $purchase = Purchase::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $wood->id,
        'quantity' => 10,
        'unit_price' => 1000,
        'total' => 10000,
        'entry_date' => now(),
    ]);
    $this->actingAs($owner)->patch("/owner/entries/purchases/{$purchase->id}/confirm")->assertRedirect();
    expect(PartnerCashMovement::outstanding($partner->id))->toBe(10000.0);

    // Owner corrects the confirmed purchase to 7,000.
    $this->actingAs($owner)->patch("/owner/entries/purchases/{$purchase->id}", [
        'head_id' => $wood->id,
        'quantity' => 7,
        'total' => 7000,
        'entry_date' => now()->toDateString(),
    ])->assertRedirect();

    // The 3,000 difference goes back to the partner's custody.
    expect(PartnerCashMovement::outstanding($partner->id))->toBe(13000.0)
        ->and(BusinessStats::all()['cash_in_hand'])->toBe(93000.0);
});

it('deducts the extra when a confirmed entry is edited upward', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    CommissionPeriod::factory()->open()->create();

    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 20000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    $wood = Item::factory()->create();
    $purchase = Purchase::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $wood->id,
        'quantity' => 10,
        'unit_price' => 1000,
        'total' => 10000,
        'entry_date' => now(),
    ]);
    $this->actingAs($owner)->patch("/owner/entries/purchases/{$purchase->id}/confirm")->assertRedirect();

    $this->actingAs($owner)->patch("/owner/entries/purchases/{$purchase->id}", [
        'head_id' => $wood->id,
        'quantity' => 14,
        'total' => 14000,
        'entry_date' => now()->toDateString(),
    ])->assertRedirect();

    expect(PartnerCashMovement::outstanding($partner->id))->toBe(6000.0);
});

it('refunds custody when a confirmed spending entry is deleted', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    CommissionPeriod::factory()->open()->create();

    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 20000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    $head = ExpenseHead::factory()->create();
    $expense = Expense::factory()->create([
        'user_id' => $partner->id,
        'expense_head_id' => $head->id,
        'amount' => 5000,
        'entry_date' => now(),
    ]);
    $this->actingAs($owner)->patch("/owner/entries/expenses/{$expense->id}/confirm")->assertRedirect();
    expect(PartnerCashMovement::outstanding($partner->id))->toBe(15000.0);

    $this->actingAs($owner)->delete("/owner/entries/expenses/{$expense->id}")->assertRedirect();

    expect(PartnerCashMovement::outstanding($partner->id))->toBe(20000.0);
});

it('refunds custody labour when a confirmed production is deleted', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    CommissionPeriod::factory()->open()->create();

    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 20000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    $chips = Item::factory()->create(['default_price' => 5000]);
    $production = Production::factory()->create([
        'user_id' => $partner->id,
        'extra_cost' => 3000,
        'entry_date' => now(),
        'status' => EntryStatus::Pending,
    ]);
    $production->outputs()->create(['item_id' => $chips->id, 'quantity' => 5]);
    $this->actingAs($owner)->patch("/owner/productions/{$production->id}/confirm")->assertRedirect();
    expect(PartnerCashMovement::outstanding($partner->id))->toBe(17000.0);

    $this->actingAs($owner)->delete("/owner/productions/{$production->id}")->assertRedirect();

    expect(PartnerCashMovement::outstanding($partner->id))->toBe(20000.0);
});

it('never moves partner cash for owner-owned entries', function (): void {
    $owner = cashOwner();
    CommissionPeriod::factory()->open()->create();
    $wood = Item::factory()->create();

    // Owner entries are auto-confirmed at creation.
    $this->actingAs($owner)->post('/entries/purchases', [
        'head_id' => $wood->id,
        'quantity' => 5,
        'total' => 5000,
        'entry_date' => now()->toDateString(),
    ])->assertRedirect();

    $purchase = Purchase::query()->latest('id')->firstOrFail();

    $this->actingAs($owner)->patch("/owner/entries/purchases/{$purchase->id}", [
        'head_id' => $wood->id,
        'quantity' => 8,
        'total' => 8000,
        'entry_date' => now()->toDateString(),
    ])->assertRedirect();

    expect(PartnerCashMovement::query()->count())->toBe(0);
});

it('reopens the cash adjustment tab after a validation error', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();

    $this->actingAs($owner)->post('/owner/partner-cash/cash-adjust', [
        'user_id' => $partner->id,
        'direction' => 'add',
        'amount' => -5,
        'occurred_at' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    // The direction the owner picked is retained and the form reopens
    // on the cash adjustment tab.
    $this->actingAs($owner)->get('/owner/partner-cash')
        ->assertSee('<option value="add" selected>', false);
});

it('auto-holds sale money in the partner custody on confirm', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    CommissionPeriod::factory()->open()->create();
    Investment::factory()->create(['amount' => 100000]);

    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 20000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    // Partner holds 20k, buys 10 units of wood (10k, confirmed).
    $wood = Item::factory()->create();
    $purchase = Purchase::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $wood->id,
        'quantity' => 10,
        'unit_price' => 1000,
        'total' => 10000,
        'entry_date' => now(),
    ]);
    $this->actingAs($owner)->patch("/owner/entries/purchases/{$purchase->id}/confirm")->assertRedirect();
    expect(PartnerCashMovement::outstanding($partner->id))->toBe(10000.0);

    // Partner sells 4 units for 8k — the sale money lands in custody.
    $sale = Sale::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $wood->id,
        'quantity' => 4,
        'unit_price' => 2000,
        'total' => 8000,
        'entry_date' => now(),
    ]);
    $this->actingAs($owner)->patch("/owner/entries/sales/{$sale->id}/confirm")->assertRedirect();

    expect(PartnerCashMovement::outstanding($partner->id))->toBe(18000.0)
        ->and(BusinessStats::all()['cash_in_hand'])->toBe(98000.0)
        ->and(PartnerCashMovement::query()->where('user_id', $partner->id)->count())->toBe(3);
});

it('syncs custody when a confirmed sale is edited or deleted', function (): void {
    $owner = cashOwner();
    $partner = cashPartner();
    CommissionPeriod::factory()->open()->create();
    Investment::factory()->create(['amount' => 100000]);

    $this->actingAs($owner)->post('/owner/partner-cash/hold', [
        'user_id' => $partner->id,
        'amount' => 20000,
        'occurred_at' => now()->toDateString(),
    ])->assertRedirect();

    $wood = Item::factory()->create();
    $purchase = Purchase::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $wood->id,
        'quantity' => 10,
        'unit_price' => 1000,
        'total' => 10000,
        'entry_date' => now(),
    ]);
    $this->actingAs($owner)->patch("/owner/entries/purchases/{$purchase->id}/confirm")->assertRedirect();

    $sale = Sale::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $wood->id,
        'quantity' => 4,
        'unit_price' => 2000,
        'total' => 8000,
        'entry_date' => now(),
    ]);
    $this->actingAs($owner)->patch("/owner/entries/sales/{$sale->id}/confirm")->assertRedirect();
    expect(PartnerCashMovement::outstanding($partner->id))->toBe(18000.0);

    // Edit the sale down to 2 units (4,000): the 4,000 difference leaves custody.
    $this->actingAs($owner)->patch("/owner/entries/sales/{$sale->id}", [
        'head_id' => $wood->id,
        'quantity' => 2,
        'total' => 4000,
        'entry_date' => now()->toDateString(),
    ])->assertRedirect();
    expect(PartnerCashMovement::outstanding($partner->id))->toBe(14000.0);

    // Deleting the sale removes the rest from custody.
    $this->actingAs($owner)->delete("/owner/entries/sales/{$sale->id}")->assertRedirect();
    expect(PartnerCashMovement::outstanding($partner->id))->toBe(10000.0);
});

it('owner-owned sales never touch partner cash', function (): void {
    $owner = cashOwner();
    CommissionPeriod::factory()->open()->create();
    Investment::factory()->create(['amount' => 100000]);

    // Stock for the owner's sale.
    $wood = Item::factory()->create();
    $purchase = Purchase::factory()->create([
        'user_id' => $owner->id,
        'item_id' => $wood->id,
        'quantity' => 10,
        'unit_price' => 1000,
        'total' => 10000,
        'entry_date' => now(),
        'status' => EntryStatus::Confirmed,
        'confirmed_by' => $owner->id,
    ]);

    // Owner's own sale is auto-confirmed at creation.
    $this->actingAs($owner)->post('/entries/sales', [
        'head_id' => $wood->id,
        'quantity' => 3,
        'total' => 6000,
        'entry_date' => now()->toDateString(),
    ])->assertRedirect();

    expect(PartnerCashMovement::query()->count())->toBe(0);
});

it('scopes the outstanding per partner and in stats', function (): void {
    $partnerA = cashPartner();
    $partnerB = cashPartner();
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partnerA->id, 'amount' => 7000]);
    PartnerCashMovement::factory()->hold()->create(['user_id' => $partnerB->id, 'amount' => 3000]);
    PartnerCashMovement::factory()->returned()->create(['user_id' => $partnerB->id, 'amount' => 1000]);

    expect(PartnerCashMovement::outstanding($partnerA->id))->toBe(7000.0)
        ->and(PartnerCashMovement::outstanding($partnerB->id))->toBe(2000.0)
        ->and(BusinessStats::all()['cash_with_partners'])->toBe(9000.0)
        ->and(BusinessStats::all($partnerA->id)['cash_with_partners'])->toBe(7000.0);
});
