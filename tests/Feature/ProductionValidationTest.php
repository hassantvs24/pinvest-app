<?php

use App\EntryStatus;
use App\Models\CommissionPeriod;
use App\Models\Item;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function prodOwner(): User
{
    return User::factory()->create([
        'role' => UserRole::Owner,
        'email' => 'owner@prod.test',
        'phone' => '01810000000',
        'preferred_language' => 'bn',
    ]);
}

function prodPartner(array $attributes = []): User
{
    return User::factory()->create(array_merge(['preferred_language' => 'bn'], $attributes));
}

function prodItem(array $attributes = []): Item
{
    return Item::factory()->create(array_merge(['unit' => 'pcs'], $attributes));
}

function prodStock(Item $item, int $quantity, float $unitPrice = 100): void
{
    Purchase::factory()->create([
        'item_id' => $item->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'total' => $quantity * $unitPrice,
        'status' => EntryStatus::Confirmed,
    ]);
}

function postProduction(User $user, Item $output, int $outputQty, array $components = [], float $extraCost = 0)
{
    return test()->actingAs($user)->post('/productions', [
        'entry_date' => now()->format('Y-m-d'),
        'extra_cost' => $extraCost,
        'outputs' => [['item_id' => $output->id, 'quantity' => $outputQty]],
        'components' => array_map(fn (Item $item, int $qty): array => ['item_id' => $item->id, 'quantity' => $qty], array_column($components, 0), array_column($components, 1)),
    ]);
}

beforeEach(function (): void {
    CommissionPeriod::factory()->open()->create();
});

it('blocks production when a component exceeds available stock', function (): void {
    $wood = prodItem(['name' => 'কাঠ']);
    $chair = prodItem(['name' => 'চেয়ার', 'default_price' => 1000]);
    prodStock($wood, 5);

    postProduction(prodPartner(), $chair, 2, [[$wood, 6]]);

    expect(Production::count())->toBe(0);
});

it('blocks production when the outputs are worth less than the inputs', function (): void {
    $wood = prodItem(['name' => 'কাঠ']);
    $chair = prodItem(['name' => 'চেয়ার', 'default_price' => 50]);
    prodStock($wood, 10); // avg cost 100/pcs

    // 5 pcs in (৳500) but outputs worth 2 × ৳50 = ৳100.
    postProduction(prodPartner(), $chair, 2, [[$wood, 5]]);

    expect(Production::count())->toBe(0);
});

it('lets a valid production through', function (): void {
    $wood = prodItem(['name' => 'কাঠ']);
    $chair = prodItem(['name' => 'চেয়ার', 'default_price' => 1000]);
    prodStock($wood, 10);

    postProduction(prodPartner(), $chair, 2, [[$wood, 5]])->assertRedirect();

    expect(Production::query()->pending()->count())->toBe(1);
});

it('reserves components for pending productions', function (): void {
    $wood = prodItem(['name' => 'কাঠ']);
    $chair = prodItem(['name' => 'চেয়ার', 'default_price' => 1000]);
    prodStock($wood, 10);

    // First run reserves 8; only 2 remain for the second run.
    postProduction(prodPartner(['phone' => '01700000081']), $chair, 1, [[$wood, 8]])->assertRedirect();
    postProduction(prodPartner(['phone' => '01700000082']), $chair, 1, [[$wood, 3]]);

    expect(Production::query()->pending()->count())->toBe(1);
});

it('blocks the owner from confirming a production that no longer fits', function (): void {
    $owner = prodOwner();
    $wood = prodItem(['name' => 'কাঠ']);
    $chair = prodItem(['name' => 'চেয়ার', 'default_price' => 1000]);
    prodStock($wood, 10);

    $production = Production::factory()->create([
        'user_id' => prodPartner(['phone' => '01700000083'])->id,
        'status' => EntryStatus::Pending,
    ]);
    $production->components()->create(['item_id' => $wood->id, 'quantity' => 10]);
    $production->outputs()->create(['item_id' => $chair->id, 'quantity' => 1]);

    // Another confirmed production consumes the wood meanwhile.
    $other = Production::factory()->create(['user_id' => $owner->id, 'status' => EntryStatus::Confirmed]);
    $other->components()->create(['item_id' => $wood->id, 'quantity' => 10]);
    $other->outputs()->create(['item_id' => prodItem(['name' => 'টেবিল', 'default_price' => 5000])->id, 'quantity' => 1]);

    $this->actingAs($owner)->patch("/owner/productions/{$production->id}/confirm")->assertRedirect();

    expect($production->fresh()->status)->toBe(EntryStatus::Pending);
});
