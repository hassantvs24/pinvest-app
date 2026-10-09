<?php

namespace Database\Seeders;

use App\EntryStatus;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Investment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the owner, demo partners, master data and a few demo entries.
     */
    public function run(): void
    {
        // Owner (single account — never self-registrable).
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner@demo.com',
            'phone' => '01900000000',
            'password' => '123456',
            'role' => UserRole::Owner,
            'commission_rate' => 0,
            'preferred_language' => 'bn',
            'is_active' => true,
        ]);

        // Demo partners.
        $karim = User::query()->create([
            'name' => 'Karim',
            'email' => 'karim@demo.com',
            'phone' => '01711111111',
            'password' => '123456',
            'role' => UserRole::Partner,
            'commission_rate' => 5,
            'preferred_language' => 'bn',
            'is_active' => true,
        ]);

        $rahim = User::query()->create([
            'name' => 'Rahim',
            'email' => 'rahim@demo.com',
            'phone' => '01822222222',
            'password' => '123456',
            'role' => UserRole::Partner,
            'commission_rate' => 7,
            'preferred_language' => 'en',
            'is_active' => true,
        ]);

        // Master data.
        foreach (['Transport', 'Labour', 'Packaging', 'Electricity', 'Rent', 'Marketing', 'Food', 'Others'] as $head) {
            ExpenseHead::query()->create(['name' => $head]);
        }

        foreach (['Raw Cotton', 'Thread', 'Button', 'Dye', 'Zipper'] as $item) {
            PurchaseItem::query()->create(['name' => $item]);
        }

        foreach ([['Shirt', 500], ['Pant', 800], ['Panjabi', 1200]] as [$item, $price]) {
            SaleItem::query()->create(['name' => $item, 'default_price' => $price]);
        }

        // Owner investment.
        Investment::query()->create([
            'amount' => 100000,
            'note' => 'Initial capital',
            'invested_at' => now()->subDays(10),
        ]);

        // Demo entries: one confirmed sale with correct 5% commission,
        // one pending expense, one pending purchase.
        Sale::query()->create([
            'user_id' => $karim->id,
            'sale_item_id' => SaleItem::query()->where('name', 'Shirt')->value('id'),
            'quantity' => 10,
            'unit_price' => 500,
            'total' => 5000,
            'commission_rate' => 5,
            'commission_amount' => 250,
            'note' => 'Demo sale',
            'entry_date' => now()->subDays(3),
            'status' => EntryStatus::Confirmed,
            'confirmed_by' => $owner->id,
            'confirmed_at' => now()->subDays(2),
        ]);

        Expense::query()->create([
            'user_id' => $karim->id,
            'expense_head_id' => ExpenseHead::query()->where('name', 'Transport')->value('id'),
            'amount' => 450,
            'note' => 'Demo expense',
            'entry_date' => now()->subDay(),
            'status' => EntryStatus::Pending,
        ]);

        Purchase::query()->create([
            'user_id' => $rahim->id,
            'purchase_item_id' => PurchaseItem::query()->where('name', 'Raw Cotton')->value('id'),
            'quantity' => 20,
            'unit_price' => 150,
            'total' => 3000,
            'note' => 'Demo purchase',
            'entry_date' => now()->subDay(),
            'status' => EntryStatus::Pending,
        ]);
    }
}
