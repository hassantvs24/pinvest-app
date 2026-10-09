<?php

namespace Database\Seeders;

use App\EntryStatus;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Investment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\RegistrationAllow;
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

        // Owner-approved registration allowances (demo: one unused number).
        RegistrationAllow::query()->create(['phone' => '01711111111', 'used_at' => now()]);
        RegistrationAllow::query()->create(['email' => 'rahim@demo.com', 'used_at' => now()]);
        RegistrationAllow::query()->create(['phone' => '01999999999']);

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
            'invested_at' => now()->subDays(40),
        ]);

        // Demo entries for LAST month so the monthly commission demo works:
        // confirmed sale + purchase + expense dated inside last month.
        $lastMonth = now()->subMonth();
        $midLastMonth = $lastMonth->copy()->startOfMonth()->addDays(14);

        Sale::query()->create([
            'user_id' => $karim->id,
            'sale_item_id' => SaleItem::query()->where('name', 'Shirt')->value('id'),
            'quantity' => 20,
            'unit_price' => 500,
            'total' => 10000,
            'commission_rate' => 5,
            'commission_amount' => 500,
            'note' => 'Last month demo sale',
            'entry_date' => $midLastMonth,
            'status' => EntryStatus::Confirmed,
            'confirmed_by' => $owner->id,
            'confirmed_at' => $midLastMonth->copy()->addDay(),
        ]);

        Sale::query()->create([
            'user_id' => $rahim->id,
            'sale_item_id' => SaleItem::query()->where('name', 'Pant')->value('id'),
            'quantity' => 5,
            'unit_price' => 800,
            'total' => 4000,
            'commission_rate' => 7,
            'commission_amount' => 280,
            'note' => 'Last month demo sale',
            'entry_date' => $midLastMonth,
            'status' => EntryStatus::Confirmed,
            'confirmed_by' => $owner->id,
            'confirmed_at' => $midLastMonth->copy()->addDay(),
        ]);

        Purchase::query()->create([
            'user_id' => $rahim->id,
            'purchase_item_id' => PurchaseItem::query()->where('name', 'Raw Cotton')->value('id'),
            'quantity' => 30,
            'unit_price' => 150,
            'total' => 4500,
            'note' => 'Last month demo purchase',
            'entry_date' => $midLastMonth->copy()->subDay(),
            'status' => EntryStatus::Confirmed,
            'confirmed_by' => $owner->id,
            'confirmed_at' => $midLastMonth,
        ]);

        Expense::query()->create([
            'user_id' => $karim->id,
            'expense_head_id' => ExpenseHead::query()->where('name', 'Transport')->value('id'),
            'amount' => 1000,
            'note' => 'Last month demo expense',
            'entry_date' => $midLastMonth->copy()->subDays(2),
            'status' => EntryStatus::Confirmed,
            'confirmed_by' => $owner->id,
            'confirmed_at' => $midLastMonth->copy()->subDay(),
        ]);

        // Pending entries for the current period (owner confirmation demo).
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

        // Closed demo period for last month with settlements:
        // profit = 14000 - 4500 - 1000 = 8500 → karim 5% = 425, rahim 7% = 595.
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        $demoPeriod = CommissionPeriod::query()->create([
            'label' => 'Demo period '.$lastMonthStart->format('M Y'),
            'opened_at' => $lastMonthStart,
            'opening_cash' => 100000,
            'status' => 'closed',
            'profit' => 8500,
            'closed_at' => $lastMonthEnd,
        ]);

        foreach ([[$karim, 5, 425.0], [$rahim, 7, 595.0]] as [$partner, $rate, $amount]) {
            CommissionSettlement::query()->create([
                'user_id' => $partner->id,
                'commission_period_id' => $demoPeriod->id,
                'period_start' => $lastMonthStart,
                'period_end' => $lastMonthEnd,
                'business_profit' => 8500,
                'commission_rate' => $rate,
                'amount' => $amount,
                'status' => 'pending',
            ]);
        }
    }
}
