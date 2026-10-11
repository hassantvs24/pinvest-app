<?php

use App\Enums\EntryStatus;
use App\Enums\ExpenseCostType;
use App\Enums\UserRole;
use App\Models\ExpenseHead;
use App\Models\Item;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockLoss;
use App\Models\User;
use App\Support\BusinessStats;
use App\Support\CashLock;
use App\Support\CommissionSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cycle flow integrity regression tests
|--------------------------------------------------------------------------
|
| Covers the cycle audit fixes: exact datetime boundaries (no same-day
| close/reopen double count), closed-cycle entry immutability, the
| service-level pending-entry close guard, the product-expense spread
| warning, and the close-preview → close commission rate drift notice.
|
*/

/**
 * Create an owner without triggering the language picker redirect.
 */
function cycleOwner(): User
{
    return User::factory()->create([
        'role' => UserRole::Owner,
        'email' => 'owner@cycle.test',
        'phone' => '01899999999',
        'preferred_language' => 'bn',
        'is_active' => true,
    ]);
}

beforeEach(fn (): ?Carbon => Carbon::setTestNow('2026-10-10 09:00'));

afterEach(fn (): ?Carbon => Carbon::setTestNow());

it('does not double-count the closing day when a cycle closes and reopens the same day', function (): void {
    $owner = cycleOwner();
    $partner = User::factory()->create([
        'role' => UserRole::Partner,
        'preferred_language' => 'bn',
        'is_active' => true,
        'commission_rate' => 10,
    ]);
    $item = Item::factory()->create();

    // Cycle 1 runs 09:00 → 15:00 with a 1000 sale at 11:00.
    $period1 = CommissionSettlementService::openPeriod('Cycle 1', now(), null, null);
    Sale::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'total' => 1000,
        'entry_date' => '2026-10-10 11:00',
        'status' => EntryStatus::Confirmed,
    ]);
    Carbon::setTestNow('2026-10-10 15:00');
    CommissionSettlementService::closePeriod($period1, now());

    // Cycle 2 opens 16:00 the same day; a 2000 sale lands at 17:00.
    Carbon::setTestNow('2026-10-10 16:00');
    $period2 = CommissionSettlementService::openPeriod('Cycle 2', now(), null, null);
    Sale::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 2000,
        'total' => 2000,
        'entry_date' => '2026-10-10 17:00',
        'status' => EntryStatus::Confirmed,
    ]);

    Carbon::setTestNow('2026-10-10 18:00');

    // The running cycle's dashboard counts only the post-close sale.
    $response = $this->actingAs($owner)->get('/dashboard');
    $response->assertOk();
    expect((float) $response->viewData('stats')['sales'])->toBe(2000.0);

    $leaderboard = $response->viewData('leaderboard');
    expect((float) $leaderboard->first()['total_sales'])->toBe(2000.0);

    // Each cycle's report contains exactly its own sale.
    $report1 = $this->actingAs($owner)->get('/owner/reports?cycle='.$period1->id);
    $report1->assertOk();
    expect((float) $report1->viewData('stats')['sales'])->toBe(1000.0);

    $report2 = $this->actingAs($owner)->get('/owner/reports?cycle='.$period2->id);
    $report2->assertOk();
    expect((float) $report2->viewData('stats')['sales'])->toBe(2000.0);
});

it('treats string boundaries as whole days and Carbon boundaries as exact moments', function (): void {
    User::factory()->create(['preferred_language' => 'bn', 'is_active' => true]);
    $item = Item::factory()->create();

    Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 500,
        'total' => 500,
        'entry_date' => '2026-10-10 14:00',
        'status' => EntryStatus::Confirmed,
    ]);

    // Whole-day strings include the sale.
    expect(BusinessStats::all(null, '2026-10-10', '2026-10-10')['sales'])->toBe(500.0);

    // An exact moment after the sale excludes it.
    expect(BusinessStats::all(null, Carbon::parse('2026-10-10 15:00'), null)['sales'])->toBe(0.0);
});

it('blocks editing and deleting entries that belong to a closed cycle', function (): void {
    $owner = cycleOwner();
    $item = Item::factory()->create();

    $period1 = CommissionSettlementService::openPeriod(null, Carbon::parse('2026-10-10 09:00'), null, null);

    Purchase::factory()->create([
        'item_id' => $item->id,
        'quantity' => 10,
        'unit_price' => 40,
        'total' => 400,
        'entry_date' => '2026-10-10 13:00',
        'status' => EntryStatus::Confirmed,
    ]);

    Carbon::setTestNow('2026-10-10 15:00');
    CommissionSettlementService::closePeriod($period1, now());

    Carbon::setTestNow('2026-10-10 16:00');
    CommissionSettlementService::openPeriod(null, now(), null, null);

    // Made at 14:00 — inside the closed cycle (close was 15:00).
    $closedSale = Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 100,
        'total' => 100,
        'entry_date' => '2026-10-10 14:00',
        'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs($owner)
        ->patch('/owner/entries/sales/'.$closedSale->id, [
            'head_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 999,
            'entry_date' => '2026-10-11',
        ])
        ->assertSessionHas('error');

    expect((float) $closedSale->fresh()->unit_price)->toBe(100.0);

    $this->actingAs($owner)
        ->delete('/owner/entries/sales/'.$closedSale->id)
        ->assertSessionHas('error');

    expect(Sale::query()->whereKey($closedSale->id)->exists())->toBeTrue();

    // An entry made AFTER the close stays editable.
    Carbon::setTestNow('2026-10-11 12:00');

    $openSale = Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 50,
        'total' => 50,
        'entry_date' => '2026-10-11 10:00',
        'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs($owner)
        ->patch('/owner/entries/sales/'.$openSale->id, [
            'head_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 75,
            'entry_date' => '2026-10-11',
        ])
        ->assertRedirect();

    expect((float) $openSale->fresh()->unit_price)->toBe(75.0);
});

it('blocks deleting productions and stock losses that belong to a closed cycle', function (): void {
    $owner = cycleOwner();
    $item = Item::factory()->create();

    $period1 = CommissionSettlementService::openPeriod(null, Carbon::parse('2026-10-10 09:00'), null, null);

    // Both made at 14:00 — inside the cycle that closes at 15:00.
    $production = Production::factory()->create([
        'user_id' => $owner->id,
        'entry_date' => '2026-10-10 14:00',
        'status' => EntryStatus::Confirmed,
    ]);
    $production->outputs()->create(['item_id' => $item->id, 'quantity' => 5]);

    $loss = StockLoss::factory()->create([
        'item_id' => $item->id,
        'entry_date' => '2026-10-10 14:00',
        'status' => EntryStatus::Confirmed,
    ]);

    Carbon::setTestNow('2026-10-10 15:00');
    CommissionSettlementService::closePeriod($period1, now());

    Carbon::setTestNow('2026-10-10 16:00');
    CommissionSettlementService::openPeriod(null, now(), null, null);

    $this->actingAs($owner)
        ->delete("/owner/productions/{$production->id}")
        ->assertSessionHas('error');

    expect(Production::query()->whereKey($production->id)->exists())->toBeTrue();

    $this->actingAs($owner)
        ->delete("/owner/stock-losses/{$loss->id}")
        ->assertSessionHas('error');

    expect(StockLoss::query()->whereKey($loss->id)->exists())->toBeTrue();

    // A run made AFTER the close stays deletable.
    Carbon::setTestNow('2026-10-11 12:00');

    $openProduction = Production::factory()->create([
        'user_id' => $owner->id,
        'entry_date' => '2026-10-11 10:00',
        'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs($owner)
        ->delete("/owner/productions/{$openProduction->id}")
        ->assertRedirect();

    expect(Production::query()->whereKey($openProduction->id)->exists())->toBeFalse();
});

it('refuses to close a cycle with pending entries at the service level', function (): void {
    $period = CommissionSettlementService::openPeriod(null, now(), null, null);

    Sale::factory()->create([
        'quantity' => 1,
        'unit_price' => 100,
        'total' => 100,
        'entry_date' => now(),
        'status' => EntryStatus::Pending,
    ]);

    expect(fn () => CommissionSettlementService::closePeriod($period, now()))
        ->toThrow(LogicException::class);

    expect($period->fresh()->status)->toBe('open');
});

it('warns when a product-cost expense is saved without a linked product', function (): void {
    $owner = cycleOwner();
    CommissionSettlementService::openPeriod(null, now(), null, null);

    $productHead = ExpenseHead::factory()->create(['cost_type' => ExpenseCostType::Product]);

    $this->actingAs($owner)
        ->post('/entries/expenses', [
            'head_id' => $productHead->id,
            'amount' => 500,
        ])
        ->assertSessionHas('warning', fn (string $message): bool => str_contains(
            $message,
            __('messages.product_expense_no_item_warning'),
        ));
});

it('flags a commission rate change between the close preview and the close', function (): void {
    $owner = cycleOwner();
    $partner = User::factory()->create([
        'role' => UserRole::Partner,
        'preferred_language' => 'bn',
        'is_active' => true,
        'commission_rate' => 10,
    ]);
    $item = Item::factory()->create();

    CommissionSettlementService::openPeriod(null, now(), null, null);
    Sale::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'total' => 1000,
        'entry_date' => now(),
        'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs($owner)->get('/owner/commissions/close')->assertOk();

    $partner->update(['commission_rate' => 20]);

    $this->actingAs($owner)
        ->post('/owner/commissions/close')
        ->assertSessionHas('success', fn (string $message): bool => str_contains(
            $message,
            __('messages.commission_rate_changed_since_preview'),
        ));
});

it('keeps the cash lock a harmless no-op off MySQL', function (): void {
    CashLock::acquire();
    CashLock::release();

    expect(DB::getDriverName())->toBe('sqlite');
})->skip(fn (): bool => DB::getDriverName() === 'mysql', 'MySQL uses the real named lock.');
