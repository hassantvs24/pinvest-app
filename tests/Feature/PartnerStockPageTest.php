<?php

use App\Enums\EntryStatus;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function partnerStockUser(): User
{
    return User::factory()->create();
}

it('shows the stock page to a partner with quantities only', function (): void {
    $item = Item::factory()->create(['name' => 'Gold Ring', 'unit' => 'pcs']);
    Purchase::factory()->create([
        'item_id' => $item->id,
        'quantity' => 7.25,
        'unit_price' => 100,
        'total' => 725,
        'status' => EntryStatus::Confirmed,
    ]);
    Sale::factory()->create([
        'item_id' => $item->id,
        'quantity' => 2.75,
        'unit_price' => 250,
        'total' => 687.5,
        'status' => EntryStatus::Pending,
    ]);

    $this->actingAs(partnerStockUser())->get('/stock')
        ->assertOk()
        ->assertSee('Gold Ring')
        ->assertSee('7.25') // on hand
        ->assertSee('2.75') // reserved by the pending sale
        ->assertSee('4.5') // sellable now
        ->assertDontSee(__('messages.stock_ledger'))
        ->assertDontSee(__('messages.avg_cost'))
        ->assertDontSee('owner/stock');
});

it('hides items with no stock from the partner stock page', function (): void {
    $emptyItem = Item::factory()->create(['name' => 'Empty Item']);
    $stockedItem = Item::factory()->create(['name' => 'Stocked Item']);
    Purchase::factory()->create([
        'item_id' => $stockedItem->id,
        'quantity' => 2,
        'unit_price' => 50,
        'total' => 100,
        'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs(partnerStockUser())->get('/stock')
        ->assertOk()
        ->assertSee('Stocked Item')
        ->assertDontSee('Empty Item');
});

it('still shows an inactive item that holds stock', function (): void {
    $item = Item::factory()->create(['name' => 'Inactive Stocked Item', 'is_active' => false]);
    Purchase::factory()->create([
        'item_id' => $item->id,
        'quantity' => 3,
        'unit_price' => 20,
        'total' => 60,
        'status' => EntryStatus::Confirmed,
    ]);

    $this->actingAs(partnerStockUser())->get('/stock')
        ->assertOk()
        ->assertSee('Inactive Stocked Item');
});

it('redirects guests to the login page', function (): void {
    $this->get('/stock')->assertRedirect(route('login'));
});
