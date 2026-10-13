<?php

use App\Enums\EntryStatus;
use App\Models\CommissionPeriod;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\StockLoss;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links owner dashboard pending chips to the right approval pages', function (): void {
    $owner = User::factory()->owner()->create(['preferred_language' => 'bn']);
    $partner = User::factory()->create();

    CommissionPeriod::factory()->open()->create();
    Purchase::factory()->create(['user_id' => $partner->id, 'status' => EntryStatus::Pending]);
    Production::factory()->create(['user_id' => $partner->id]);
    StockLoss::factory()->create(['user_id' => $partner->id, 'status' => EntryStatus::Pending]);

    $this->actingAs($owner)->get('/dashboard')
        ->assertOk()
        ->assertSee('/owner/entries?status=pending')
        ->assertSee('/owner/productions')
        ->assertSee('/owner/stock-losses');
});

it('links partner dashboard pending chips to the right lists', function (): void {
    $partner = User::factory()->create(['preferred_language' => 'bn']);

    CommissionPeriod::factory()->open()->create();
    Purchase::factory()->create(['user_id' => $partner->id, 'status' => EntryStatus::Pending]);
    Production::factory()->create(['user_id' => $partner->id]);
    StockLoss::factory()->create(['user_id' => $partner->id, 'status' => EntryStatus::Pending]);

    $this->actingAs($partner)->get('/dashboard')
        ->assertOk()
        ->assertSee('/entries/purchases?status=pending')
        ->assertSee('/productions')
        ->assertSee('/stock-losses');
});
