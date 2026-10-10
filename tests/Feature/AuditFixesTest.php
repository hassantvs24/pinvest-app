<?php

use App\Enums\UserRole;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Investment;
use App\Models\Item;
use App\Models\PayoutRequest;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Support\InventoryService;
use Database\Seeders\DefaultDataSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Final audit regression tests
|--------------------------------------------------------------------------
|
| Covers the fixes from the pre-release audit: fractional quantities,
| logout without a chosen language, inactive-partner login block,
| entry dates locked to the open cycle, payout/partner-delete guards
| and demo-seeder cash correctness.
|
*/

function auditOwner(): User
{
    return User::factory()->create([
        'role' => UserRole::Owner,
        'email' => 'owner@audit.test',
        'phone' => '01811111111',
        'preferred_language' => 'bn',
    ]);
}

function auditPartner(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => UserRole::Partner,
        'preferred_language' => 'bn',
        'commission_rate' => 10,
    ], $attributes));
}

function auditPeriod(): CommissionPeriod
{
    return CommissionPeriod::factory()->open()->create(['opened_at' => now()->subDays(3)]);
}

it('accepts fractional quantities end to end (purchase, sale, stock, COGS)', function (): void {
    $owner = auditOwner();
    $partner = auditPartner();
    $item = Item::factory()->create(['unit' => 'tola']);
    auditPeriod();

    // Buy 2.5 tola.
    $this->actingAs($owner)->post('/entries/purchases', [
        'head_id' => $item->id,
        'quantity' => 2.5,
        'unit_price' => 1000,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertSessionDoesntHaveErrors()->assertRedirect();

    $purchase = Purchase::sole();
    expect((float) $purchase->quantity)->toBe(2.5);
    expect((float) $purchase->total)->toBe(2500.0);

    // Sell 1.25 tola — half of the stock must remain.
    $this->actingAs($partner)->post('/entries/sales', [
        'head_id' => $item->id,
        'quantity' => 1.25,
        'unit_price' => 2000,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertSessionDoesntHaveErrors()->assertRedirect();

    expect((float) Sale::sole()->quantity)->toBe(1.25);

    $owner->unsetRelation('sales');
    $this->actingAs($owner)->patch('/owner/entries/sales/'.Sale::sole()->id.'/confirm')->assertRedirect();

    $rows = collect(InventoryService::stockRows(now()));
    $row = $rows->firstWhere('item.id', $item->id);
    expect(round($row['quantity'], 3))->toBe(1.25);

    // COGS for the sold 1.25 tola at avg cost 1000/tola.
    expect(InventoryService::cogs(now()->startOfDay(), now()))->toBe(1250.0);
});

it('rejects non-numeric or zero fractional quantities', function (): void {
    $owner = auditOwner();
    $item = Item::factory()->create();
    auditPeriod();

    $this->actingAs($owner)->post('/entries/purchases', [
        'head_id' => $item->id,
        'quantity' => 0,
        'unit_price' => 100,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertSessionHasErrors('quantity');

    expect(Purchase::query()->count())->toBe(0);
});

it('lets a user without a chosen language log out', function (): void {
    $partner = auditPartner(['preferred_language' => null]);

    $this->actingAs($partner)->post('/logout')->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});

it('blocks login for a deactivated partner', function (): void {
    $partner = auditPartner(['is_active' => false]);

    $this->post('/login', [
        'identifier' => $partner->phone,
        'password' => 'password',
    ])->assertSessionHasErrors('identifier');

    expect(auth()->check())->toBeFalse();
});

it('rejects owner entries dated inside a closed cycle', function (): void {
    $owner = auditOwner();
    $item = Item::factory()->create();

    auditPeriod(); // opened 3 days ago
    $this->actingAs($owner)->post('/owner/commissions/close', [
        'closed_at' => now()->subDays(2)->format('Y-m-d'),
    ])->assertRedirect();

    // Reopen today; backdating into the closed cycle must fail.
    $this->actingAs($owner)->post('/owner/commissions/open', [
        'opened_at' => now()->format('Y-m-d'),
    ])->assertSessionDoesntHaveErrors();

    $this->actingAs($owner)->post('/entries/purchases', [
        'head_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 100,
        'entry_date' => now()->subDays(3)->format('Y-m-d'),
    ])->assertSessionHasErrors('entry_date');

    expect(Purchase::query()->count())->toBe(0);

    // A same-day entry is fine.
    $this->actingAs($owner)->post('/entries/purchases', [
        'head_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 100,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertSessionDoesntHaveErrors();

    expect(Purchase::query()->count())->toBe(1);
});

it('blocks payout approval when cash in hand is insufficient', function (): void {
    $owner = auditOwner();
    $partner = auditPartner();
    $period = auditPeriod();

    CommissionSettlement::factory()->create([
        'user_id' => $partner->id,
        'commission_period_id' => $period->id,
        'amount' => 5000,
        'status' => 'pending',
    ]);

    $request = PayoutRequest::factory()->create([
        'user_id' => $partner->id,
        'amount' => 5000,
        'status' => 'pending',
    ]);

    // No cash at all (no investment/sales): payout must be refused.
    $this->actingAs($owner)
        ->patch('/owner/commissions/requests/'.$request->id.'/approve')
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($request->fresh()->status)->toBe('pending');
    expect(CommissionSettlement::sole()->status)->toBe('pending');
});

it('blocks deleting a partner with unpaid commission', function (): void {
    $owner = auditOwner();
    $partner = auditPartner();
    $period = auditPeriod();

    CommissionSettlement::factory()->create([
        'user_id' => $partner->id,
        'commission_period_id' => $period->id,
        'amount' => 500,
        'status' => 'pending',
    ]);

    $this->actingAs($owner)->delete('/owner/partners/'.$partner->id)->assertRedirect();

    expect(User::query()->whereKey($partner->id)->exists())->toBeTrue();
});

it('keeps demo capital counted exactly once', function (): void {
    $this->seed(DefaultDataSeeder::class);
    $this->seed(DemoDataSeeder::class);

    // The initial capital lives in ONE place: the investment entry.
    // The first demo period must not add it again as opening cash.
    expect((float) Investment::query()->sum('amount'))->toBe(100000.0);
    expect((float) CommissionPeriod::query()->sum('opening_cash'))->toBe(0.0);
});
