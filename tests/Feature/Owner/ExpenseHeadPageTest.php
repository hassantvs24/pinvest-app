<?php

use App\EntryStatus;
use App\Enums\ExpenseCostType;
use App\Models\CommissionPeriod;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Item;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function headOwner(): User
{
    return User::factory()->create([
        'role' => UserRole::Owner,
        'preferred_language' => 'bn',
    ]);
}

function seedExpense(ExpenseHead $head, float $amount, EntryStatus $status = EntryStatus::Confirmed): Expense
{
    return Expense::factory()->create([
        'expense_head_id' => $head->id,
        'amount' => $amount,
        'status' => $status,
    ]);
}

it('shows the expense heads page to the owner but blocks partners', function (): void {
    $head = ExpenseHead::factory()->create(['name' => 'পরিবহন ভাড়া']);
    CommissionPeriod::factory()->open()->create();
    seedExpense($head, 500);

    $this->actingAs(headOwner())->get('/owner/expense-heads')->assertOk()->assertSee('পরিবহন ভাড়া');

    $partner = User::factory()->create(['preferred_language' => 'bn']);
    $this->actingAs($partner)->get('/owner/expense-heads')->assertForbidden();
    $this->actingAs($partner)->get("/owner/expense-heads/{$head->id}")->assertForbidden();
});

it('ranks heads by confirmed spending with the top head first and shares', function (): void {
    $transport = ExpenseHead::factory()->create(['name' => 'পরিবহন']);
    $labour = ExpenseHead::factory()->create(['name' => 'মজুরি']);
    CommissionPeriod::factory()->open()->create();

    seedExpense($labour, 3000);
    seedExpense($labour, 1000);
    seedExpense($transport, 500);

    $response = $this->actingAs(headOwner())->get('/owner/expense-heads')->assertOk();

    // Higher-spending head comes first and carries the top badge.
    $response->assertSeeInOrder(['মজুরি', 'পরিবহন'])
        ->assertSee('4,000.00')
        ->assertSee('88.9%') // 4000 of 4500
        ->assertSee('৳4,500.00'); // grand total

    // Pending amounts are memo-only, not ranked.
    seedExpense($transport, 99999, EntryStatus::Pending);
    $this->actingAs(headOwner())->get('/owner/expense-heads')
        ->assertSeeInOrder(['মজুরি', 'পরিবহন'])
        ->assertSee('99,999.00');
});

it('shows one head ledger with entries, linked product and pending memo', function (): void {
    $head = ExpenseHead::factory()->create(['name' => 'প্রসেসিং', 'cost_type' => ExpenseCostType::Product]);
    $item = Item::factory()->create(['name' => 'উদ চিপস']);
    CommissionPeriod::factory()->open()->create();

    seedExpense($head, 700)->update(['item_id' => $item->id]);
    seedExpense($head, 300);
    seedExpense($head, 450, EntryStatus::Pending);

    $this->actingAs(headOwner())->get("/owner/expense-heads/{$head->id}")
        ->assertOk()
        ->assertSee('প্রসেসিং')
        ->assertSee('1,000.00') // confirmed total
        ->assertSee('700.00')
        ->assertSee('উদ চিপস') // linked product badge
        ->assertSee('450.00'); // pending memo
});

it('paginates long head ledgers', function (): void {
    $head = ExpenseHead::factory()->create(['name' => 'খাত']);
    CommissionPeriod::factory()->open()->create();

    foreach (range(1, 35) as $i) {
        Expense::factory()->create([
            'expense_head_id' => $head->id,
            'amount' => $i,
            'status' => EntryStatus::Confirmed,
            'entry_date' => now()->subDays(40 - $i),
        ]);
    }

    $this->actingAs(headOwner())->get("/owner/expense-heads/{$head->id}")->assertOk();
    $this->actingAs(headOwner())->get("/owner/expense-heads/{$head->id}?page=2")->assertOk();
});
