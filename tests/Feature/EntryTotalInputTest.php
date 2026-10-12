<?php

use App\Enums\EntryStatus;
use App\Enums\UserRole;
use App\Models\CommissionPeriod;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function entryTotalOwner(): User
{
    return User::factory()->create([
        'role' => UserRole::Owner,
        'email' => 'owner@entrytotal.test',
        'phone' => '01822222222',
        'preferred_language' => 'bn',
    ]);
}

function entryTotalPartner(array $attributes = []): User
{
    return User::factory()->create(array_merge(['preferred_language' => 'bn'], $attributes));
}

beforeEach(function (): void {
    CommissionPeriod::factory()->open()->create();
});

it('computes the unit price from quantity and total when creating a purchase', function (): void {
    $owner = entryTotalOwner();
    $item = Item::factory()->create(['unit' => 'gram']);

    $this->actingAs($owner)->post('/entries/purchases', [
        'head_id' => $item->id,
        'quantity' => 200,
        'total' => 20000,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertSessionDoesntHaveErrors()->assertRedirect();

    $purchase = Purchase::sole();

    expect((float) $purchase->unit_price)->toBe(100.0)
        ->and((float) $purchase->total)->toBe(20000.0)
        ->and((float) $purchase->quantity)->toBe(200.0);
});

it('computes the unit price from quantity and total when creating a sale', function (): void {
    $partner = entryTotalPartner();
    $item = Item::factory()->create(['unit' => 'gram']);
    Purchase::factory()->create([
        'item_id' => $item->id,
        'quantity' => 200,
        'unit_price' => 50,
        'total' => 10000,
        'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs($partner)->post('/entries/sales', [
        'head_id' => $item->id,
        'quantity' => 100,
        'total' => 15000,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertSessionDoesntHaveErrors()->assertRedirect();

    $sale = Sale::sole();

    expect((float) $sale->unit_price)->toBe(150.0)
        ->and((float) $sale->total)->toBe(15000.0);
});

it('recomputes the unit price when the owner edits a sale total', function (): void {
    $owner = entryTotalOwner();
    $item = Item::factory()->create();
    Purchase::factory()->create([
        'item_id' => $item->id,
        'quantity' => 10,
        'status' => EntryStatus::Confirmed,
    ]);

    $sale = Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 2,
        'unit_price' => 100,
        'total' => 200,
        'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs($owner)->patch("/owner/entries/sales/{$sale->id}", [
        'head_id' => $item->id,
        'quantity' => 4,
        'total' => 500,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertRedirect();

    expect((float) $sale->fresh()->unit_price)->toBe(125.0)
        ->and((float) $sale->fresh()->total)->toBe(500.0);
});

it('rejects a total whose computed unit price rounds below 0.01', function (): void {
    $owner = entryTotalOwner();
    $item = Item::factory()->create();

    $this->actingAs($owner)->post('/entries/purchases', [
        'head_id' => $item->id,
        'quantity' => 100,
        'total' => 0.49,
        'entry_date' => now()->format('Y-m-d'),
    ])->assertSessionHasErrors('total');

    expect(Purchase::count())->toBe(0);
});

it('renders the create form with a total input and no unit price input', function (): void {
    $partner = entryTotalPartner();
    Item::factory()->create();

    $html = $this->actingAs($partner)->get('/entries/purchases/create')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('name="total"')
        ->and($html)->not->toContain('name="unit_price"');
});
