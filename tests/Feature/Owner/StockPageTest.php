<?php

use App\EntryStatus;
use App\Models\Item;
use App\Models\Production;
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
    Purchase::factory()->create(['item_id' => $item->id, 'quantity' => 10, 'unit_price' => 100, 'total' => 1000, 'status' => EntryStatus::Confirmed]);
    Sale::factory()->create(['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 250, 'total' => 500, 'status' => EntryStatus::Confirmed]);

    $this->actingAs(stockOwner())->get('/owner/stock')
        ->assertOk()
        ->assertSee('Gold Ring')
        ->assertSee('100.00') // avg purchase cost per unit
        ->assertSee('250.00'); // avg sale price per unit

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
    Purchase::factory()->create([
        'item_id' => $item->id,
        'quantity' => 10,
        'unit_price' => 100,
        'total' => 1000,
        'status' => EntryStatus::Confirmed,
    ]);
    Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 4,
        'unit_price' => 300,
        'total' => 1200,
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
        ->and($ledger['rows'][0]['unit_price'])->toBe(100.0)
        ->and($ledger['rows'][0]['total'])->toBe(1000.0)
        ->and($ledger['rows'][1]['kind'])->toBe('sale')
        ->and($ledger['rows'][1]['balance'])->toBe(6.0)
        ->and($ledger['rows'][1]['unit_price'])->toBe(300.0)
        ->and($ledger['rows'][1]['total'])->toBe(1200.0)
        ->and($ledger['pending'])->toHaveCount(1)
        ->and($ledger['pending'][0]['quantity'])->toBe(2.0)
        ->and($ledger['totals']['purchase']['qty'])->toBe(10.0)
        ->and($ledger['totals']['purchase']['amount'])->toBe(1000.0)
        ->and($ledger['totals']['sale']['qty'])->toBe(4.0)
        ->and($ledger['totals']['sale']['amount'])->toBe(1200.0);

    $this->actingAs(stockOwner())->get("/owner/stock/{$item->id}")
        ->assertOk()
        ->assertSee('Silver Chain')
        ->assertSee('6');
});

it('paginates long ledgers without breaking running balances', function (): void {
    $item = Item::factory()->create(['name' => 'Paged Item', 'unit' => 'pcs']);
    foreach (range(1, 35) as $i) {
        Purchase::factory()->create([
            'item_id' => $item->id,
            'quantity' => 1,
            'status' => EntryStatus::Confirmed,
            'entry_date' => now()->subDays(40 - $i),
        ]);
    }

    $page1 = $this->actingAs(stockOwner())->get("/owner/stock/{$item->id}");
    $page1->assertOk();

    $page2 = $this->actingAs(stockOwner())->get("/owner/stock/{$item->id}?page=2");
    $page2->assertOk();

    // Page 2 has the 5 newest rows; the last running balance is the
    // full-history total of 35.
    $ledger = InventoryService::ledger($item, now());
    expect($ledger['rows'])->toHaveCount(35)
        ->and($ledger['rows'][34]['balance'])->toBe(35.0);
});

it('shows production cost in the ledger at the allocated average cost', function (): void {
    $wood = Item::factory()->create(['name' => 'কাঠ', 'unit' => 'pcs']);
    $chair = Item::factory()->create(['name' => 'চেয়ার', 'unit' => 'pcs', 'default_price' => 500]);
    Purchase::factory()->create([
        'item_id' => $wood->id,
        'quantity' => 10,
        'unit_price' => 100,
        'total' => 1000,
        'status' => EntryStatus::Confirmed,
    ]);

    $production = Production::factory()->create([
        'extra_cost' => 500,
        'status' => EntryStatus::Confirmed,
    ]);
    $production->components()->create(['item_id' => $wood->id, 'quantity' => 5]);
    $production->outputs()->create(['item_id' => $chair->id, 'quantity' => 2]);

    $chairLedger = InventoryService::ledger($chair, now());

    expect($chairLedger['rows'])->toHaveCount(1)
        ->and($chairLedger['rows'][0]['kind'])->toBe('production')
        ->and($chairLedger['rows'][0]['unit_price'])->toBe(500.0)
        ->and($chairLedger['rows'][0]['total'])->toBe(1000.0);

    // The component side shows what the wood cost when it was consumed.
    $woodLedger = InventoryService::ledger($wood, now());
    expect($woodLedger['rows'][1]['kind'])->toBe('production')
        ->and($woodLedger['rows'][1]['unit_price'])->toBe(100.0)
        ->and($woodLedger['rows'][1]['total'])->toBe(500.0);
});
