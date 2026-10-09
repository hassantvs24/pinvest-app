<?php

use App\EntryStatus;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Support\InventoryService;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function stockOwner(): User
{
    return User::factory()->create([
        'role' => UserRole::Owner,
        'preferred_language' => 'bn',
    ]);
}

it('shows the stock page to the owner but blocks partners', function (): void {
    $item = Item::factory()->create(['name' => 'Gold Ring']);
    Purchase::factory()->create(['item_id' => $item->id, 'quantity' => 10, 'status' => EntryStatus::Confirmed]);

    $this->actingAs(stockOwner())->get('/owner/stock')->assertOk()->assertSee('Gold Ring');

    $partner = User::factory()->create(['preferred_language' => 'bn']);
    $this->actingAs($partner)->get('/owner/stock')->assertForbidden();
    $this->actingAs($partner)->get("/owner/stock/{$item->id}")->assertForbidden();
});

it('lists active items even with zero stock', function (): void {
    $item = Item::factory()->create(['name' => 'Empty Item']);

    $this->actingAs(stockOwner())->get('/owner/stock')->assertOk()->assertSee('Empty Item');
});

it('shows the movement ledger with running balance and pending reservations', function (): void {
    $item = Item::factory()->create(['name' => 'Silver Chain', 'unit' => 'pcs']);
    Purchase::factory()->create(['item_id' => $item->id, 'quantity' => 10, 'status' => EntryStatus::Confirmed]);
    Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 4,
        'status' => EntryStatus::Confirmed,
        'entry_date' => now(),
    ]);
    Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 2,
        'status' => EntryStatus::Pending,
        'entry_date' => now(),
    ]);

    $ledger = InventoryService::ledger($item, now());

    expect($ledger['rows'])->toHaveCount(2)
        ->and($ledger['rows'][0]['kind'])->toBe('purchase')
        ->and($ledger['rows'][0]['balance'])->toBe(10.0)
        ->and($ledger['rows'][1]['kind'])->toBe('sale')
        ->and($ledger['rows'][1]['balance'])->toBe(6.0)
        ->and($ledger['pending'])->toHaveCount(1)
        ->and($ledger['pending'][0]['quantity'])->toBe(2.0);

    $this->actingAs(stockOwner())->get("/owner/stock/{$item->id}")
        ->assertOk()
        ->assertSee('Silver Chain')
        ->assertSee('6');
});
