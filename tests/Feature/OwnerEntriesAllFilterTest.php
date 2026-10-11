<?php

use App\Enums\EntryStatus;
use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function allFilterOwner(): User
{
    return User::factory()->create([
        'role' => UserRole::Owner,
        'preferred_language' => 'bn',
    ]);
}

function allFilterPartner(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => UserRole::Partner,
        'preferred_language' => 'bn',
        'is_active' => true,
    ], $attributes));
}

it('shows entries of every type by default with the all option selected', function (): void {
    $owner = allFilterOwner();
    $partner = allFilterPartner();

    $head = ExpenseHead::factory()->create(['name' => 'ভাড়া ALLTEST']);
    $purchaseItem = Item::factory()->create(['name' => 'চিনি ALLTEST']);
    $saleItem = Item::factory()->create(['name' => 'লবণ ALLTEST']);

    Expense::factory()->create([
        'user_id' => $partner->id,
        'expense_head_id' => $head->id,
        'entry_date' => '2026-10-11 09:00',
    ]);
    Purchase::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $purchaseItem->id,
        'entry_date' => '2026-10-09 09:00',
    ]);
    Sale::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $saleItem->id,
        'entry_date' => '2026-10-10 09:00',
    ]);

    $this->actingAs($owner)->get('/owner/entries')
        ->assertOk()
        // The type filter defaults to "all".
        ->assertSee('value="all" selected', escape: false)
        // Newest entry first across the merged types.
        ->assertSeeInOrder(['ভাড়া ALLTEST', 'লবণ ALLTEST', 'চিনি ALLTEST'])
        // Each card carries its own type badge for the mixed list.
        ->assertSee('💸 খরচ', escape: false)
        ->assertSee('🛒 ক্রয়', escape: false)
        ->assertSee('💰 বিক্রি', escape: false);
});

it('applies status and partner filters across all types', function (): void {
    $owner = allFilterOwner();
    $partnerA = allFilterPartner(['phone' => '01700000001']);
    $partnerB = allFilterPartner(['phone' => '01700000002']);

    $head = ExpenseHead::factory()->create(['name' => 'খাবার ALLTEST']);
    $item = Item::factory()->create(['name' => 'ঘি ALLTEST']);

    // Partner A: one pending expense, one confirmed sale.
    $expense = Expense::factory()->create([
        'user_id' => $partnerA->id,
        'expense_head_id' => $head->id,
        'status' => EntryStatus::Pending,
    ]);
    $sale = Sale::factory()->create([
        'user_id' => $partnerA->id,
        'item_id' => $item->id,
        'status' => EntryStatus::Confirmed,
    ]);
    // Partner B: one pending purchase — must stay hidden by the partner filter.
    $purchase = Purchase::factory()->create([
        'user_id' => $partnerB->id,
        'item_id' => $item->id,
        'status' => EntryStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->get("/owner/entries?type=all&status=pending&partner_id={$partnerA->id}")
        ->assertOk()
        ->assertSee('খাবার ALLTEST')
        // Only partner A's pending expense makes it through both filters;
        // the action URL is rendered only on the matching entry's card.
        ->assertSee("owner/entries/expenses/{$expense->id}", escape: false)
        ->assertDontSee("owner/entries/sales/{$sale->id}", escape: false)
        ->assertDontSee("owner/entries/purchases/{$purchase->id}", escape: false);
});

it('keeps single-type mode working with the per-entry actions', function (): void {
    $owner = allFilterOwner();
    $partner = allFilterPartner();

    $head = ExpenseHead::factory()->create(['name' => 'পরিবহন ALLTEST']);
    $purchaseItem = Item::factory()->create(['name' => 'চাল ALLTEST']);
    $saleItem = Item::factory()->create(['name' => 'ডাল ALLTEST']);

    $expense = Expense::factory()->create([
        'user_id' => $partner->id,
        'expense_head_id' => $head->id,
    ]);
    $purchase = Purchase::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $purchaseItem->id,
        'status' => EntryStatus::Pending,
    ]);
    $sale = Sale::factory()->create([
        'user_id' => $partner->id,
        'item_id' => $saleItem->id,
    ]);

    $this->actingAs($owner)->get('/owner/entries?type=purchases')
        ->assertOk()
        ->assertSee('চাল ALLTEST')
        // Only the purchase card renders; action forms point at purchases routes.
        ->assertSee("owner/entries/purchases/{$purchase->id}/confirm", escape: false)
        ->assertDontSee("owner/entries/expenses/{$expense->id}", escape: false)
        ->assertDontSee("owner/entries/sales/{$sale->id}", escape: false);

    $this->actingAs($owner)->get('/owner/entries?type=expenses')
        ->assertOk()
        ->assertSee('পরিবহন ALLTEST')
        ->assertSee("owner/entries/expenses/{$expense->id}", escape: false);
});

it('still 404s on an unknown entry type', function (): void {
    $this->actingAs(allFilterOwner())->get('/owner/entries?type=bogus')->assertNotFound();
});
