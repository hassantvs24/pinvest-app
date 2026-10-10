<?php

use App\Enums\EntryStatus;
use App\Enums\UserRole;
use App\Models\CommissionPeriod;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockLoss;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function limitOwner(): User
{
    return User::factory()->create([
        'role' => UserRole::Owner,
        'email' => 'owner@limit.test',
        'phone' => '01800000000',
        'preferred_language' => 'bn',
    ]);
}

function limitPartner(array $attributes = []): User
{
    return User::factory()->create(array_merge(['preferred_language' => 'bn'], $attributes));
}

function stockItem(array $attributes = []): Item
{
    return Item::factory()->create($attributes);
}

function seedStock(Item $item, int $quantity): void
{
    Purchase::factory()->create([
        'item_id' => $item->id,
        'quantity' => $quantity,
        'status' => EntryStatus::Confirmed,
    ]);
}

function postSale(User $user, Item $item, int $quantity)
{
    return test()->actingAs($user)->post('/entries/sales', [
        'head_id' => $item->id,
        'quantity' => $quantity,
        'unit_price' => 100,
        'entry_date' => now()->format('Y-m-d'),
    ]);
}

beforeEach(function (): void {
    CommissionPeriod::factory()->open()->create();
});

it('blocks a sale above available stock entirely', function (): void {
    $item = stockItem();
    seedStock($item, 5);

    postSale(limitPartner(), $item, 6);

    expect(Sale::count())->toBe(0);
});

it('lets a sale up to the available stock through', function (): void {
    $item = stockItem();
    seedStock($item, 5);

    postSale(limitPartner(), $item, 5)->assertRedirect();

    expect(Sale::count())->toBe(1);
});

it('reserves stock for pending sales so two partners cannot oversell', function (): void {
    $item = stockItem();
    seedStock($item, 5);

    // First partner takes 4 (pending) — only 1 remains sellable.
    postSale(limitPartner(['phone' => '01700000001']), $item, 4)->assertRedirect();
    postSale(limitPartner(['phone' => '01700000002']), $item, 2);
    expect(Sale::count())->toBe(1);

    // Exactly the remaining 1 still works.
    postSale(limitPartner(['phone' => '01700000003']), $item, 1)->assertRedirect();
    expect(Sale::count())->toBe(2);
});

it('blocks the owner from confirming a pending sale that no longer fits', function (): void {
    $item = stockItem();
    seedStock($item, 5);
    $owner = limitOwner();

    $sale = Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 5,
        'status' => EntryStatus::Pending,
    ]);
    // Another confirmed sale consumes the stock meanwhile.
    Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 5,
        'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs($owner)->patch("/owner/entries/sales/{$sale->id}/confirm")->assertRedirect();

    expect($sale->fresh()->status)->toBe(EntryStatus::Pending);
});

it('blocks the owner from editing a sale beyond available stock', function (): void {
    $item = stockItem();
    seedStock($item, 5);
    $owner = limitOwner();

    $sale = Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 3,
        'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs($owner)->patch("/owner/entries/sales/{$sale->id}", [
        'head_id' => $item->id,
        'quantity' => 6,
        'unit_price' => 100,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertRedirect();

    expect((float) $sale->fresh()->quantity)->toBe(3.0);
});

it('blocks the owner from editing a pending sale above what is left', function (): void {
    $item = stockItem();
    seedStock($item, 5);
    $owner = limitOwner();

    $sale = Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 2,
        'status' => EntryStatus::Pending,
    ]);
    Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 3,
        'status' => EntryStatus::Pending,
    ]);

    // 2 reserved by others (3) leaves 2 for this entry; asking 3 must fail.
    $this->actingAs($owner)->patch("/owner/entries/sales/{$sale->id}", [
        'head_id' => $item->id,
        'quantity' => 3,
        'unit_price' => 100,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertRedirect();

    expect((float) $sale->fresh()->quantity)->toBe(2.0);
});

it('blocks a partner stock loss above available stock', function (): void {
    $item = stockItem();
    seedStock($item, 5);

    $this->actingAs(limitPartner())->post('/stock-losses', [
        'item_id' => $item->id,
        'quantity' => 6,
        'note' => 'broken',
    ])->assertRedirect();

    expect(StockLoss::count())->toBe(0);
});

it('blocks an owner stock loss above available stock', function (): void {
    $item = stockItem();
    seedStock($item, 5);
    $owner = limitOwner();

    $this->actingAs($owner)->post('/owner/stock-losses', [
        'item_id' => $item->id,
        'quantity' => 6,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertRedirect();

    expect(StockLoss::count())->toBe(0);

    // Within stock still works.
    $this->actingAs($owner)->post('/owner/stock-losses', [
        'item_id' => $item->id,
        'quantity' => 5,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertRedirect();

    expect(StockLoss::count())->toBe(1);
});

it('reserves stock for pending losses so sales cannot oversell the rest', function (): void {
    $item = stockItem();
    seedStock($item, 5);

    // Pending loss of 4 leaves only 1 sellable.
    StockLoss::factory()->create([
        'item_id' => $item->id,
        'quantity' => 4,
        'status' => EntryStatus::Pending,
    ]);

    postSale(limitPartner(), $item, 2);
    expect(Sale::count())->toBe(0);

    postSale(limitPartner(['phone' => '01700000004']), $item, 1)->assertRedirect();
    expect(Sale::count())->toBe(1);
});

it('blocks the owner from confirming a pending loss that no longer fits', function (): void {
    $item = stockItem();
    seedStock($item, 5);

    $loss = StockLoss::factory()->create([
        'item_id' => $item->id,
        'quantity' => 5,
        'status' => EntryStatus::Pending,
    ]);
    // A confirmed sale consumes the stock meanwhile.
    Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 5,
        'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs(limitOwner())->patch("/owner/stock-losses/{$loss->id}/confirm")->assertRedirect();

    expect($loss->fresh()->status)->toBe(EntryStatus::Pending);
});
