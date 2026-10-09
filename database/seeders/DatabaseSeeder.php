<?php

namespace Database\Seeders;

use App\EntryStatus;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Investment;
use App\Models\Payout;
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
     * Seed the owner, demo partners, master data, and ten months of
     * closed commission cycles with settlements and payouts.
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

        foreach ([['Raw Cotton', 'kg'], ['Thread', 'pcs'], ['Button', 'pcs'], ['Dye', 'ml'], ['Zipper', 'pcs']] as [$item, $unit]) {
            PurchaseItem::query()->create(['name' => $item, 'unit' => $unit]);
        }

        foreach ([['Shirt', 500, 'pcs'], ['Pant', 800, 'pcs'], ['Panjabi', 1200, 'pcs']] as [$item, $price, $unit]) {
            SaleItem::query()->create(['name' => $item, 'default_price' => $price, 'unit' => $unit]);
        }

        // Owner investment.
        Investment::query()->create([
            'amount' => 100000,
            'note' => 'Initial capital',
            'invested_at' => now()->subMonths(11)->startOfMonth(),
        ]);

        $shirtId = SaleItem::query()->where('name', 'Shirt')->value('id');
        $pantId = SaleItem::query()->where('name', 'Pant')->value('id');
        $cottonId = PurchaseItem::query()->where('name', 'Raw Cotton')->value('id');
        $transportId = ExpenseHead::query()->where('name', 'Transport')->value('id');

        // Ten closed commission cycles (last 10 full months).
        // Month index 4 is a deliberate LOSS month to demo the loss state.
        for ($i = 10; $i >= 1; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = now()->subMonths($i)->endOfMonth();
            $mid = $start->copy()->addDays(14);

            if ($i === 4) {
                // Loss month: expense exceeds sales.
                Sale::query()->create([
                    'user_id' => $karim->id,
                    'sale_item_id' => $shirtId,
                    'quantity' => 3,
                    'unit_price' => 500,
                    'total' => 1500,
                    'note' => 'Slow month',
                    'entry_date' => $mid,
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid->copy()->addDay(),
                ]);
                Expense::query()->create([
                    'user_id' => $karim->id,
                    'expense_head_id' => $transportId,
                    'amount' => 3000,
                    'note' => 'Big transport bill',
                    'entry_date' => $mid->copy()->subDay(),
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid,
                ]);

                $profit = -1500.0;
            } else {
                // Regular profitable month.
                Sale::query()->create([
                    'user_id' => $karim->id,
                    'sale_item_id' => $shirtId,
                    'quantity' => 10 + $i,
                    'unit_price' => 500,
                    'total' => (10 + $i) * 500,
                    'note' => 'Monthly sale',
                    'entry_date' => $mid,
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid->copy()->addDay(),
                ]);
                Sale::query()->create([
                    'user_id' => $rahim->id,
                    'sale_item_id' => $pantId,
                    'quantity' => 4 + $i,
                    'unit_price' => 800,
                    'total' => (4 + $i) * 800,
                    'note' => 'Monthly sale',
                    'entry_date' => $mid,
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid->copy()->addDay(),
                ]);
                Purchase::query()->create([
                    'user_id' => $rahim->id,
                    'purchase_item_id' => $cottonId,
                    'quantity' => 20 + $i,
                    'unit_price' => 150,
                    'total' => (20 + $i) * 150,
                    'note' => 'Monthly purchase',
                    'entry_date' => $mid->copy()->subDay(),
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid,
                ]);
                Expense::query()->create([
                    'user_id' => $karim->id,
                    'expense_head_id' => $transportId,
                    'amount' => 800 + $i * 50,
                    'note' => 'Monthly transport',
                    'entry_date' => $mid->copy()->subDays(2),
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid->copy()->subDay(),
                ]);

                $profit = ((10 + $i) * 500 + (4 + $i) * 800) - ((20 + $i) * 150) - (800 + $i * 50);
            }

            $period = CommissionPeriod::query()->create([
                'label' => $start->format('F Y'),
                'opened_at' => $start,
                'opening_cash' => $i === 10 ? 100000 : null,
                'status' => 'closed',
                'profit' => $profit,
                'closed_at' => $end,
            ]);

            if ($profit > 0) {
                // Older cycles are paid out; the two most recent stay pending
                // so the payout-request demo flow can be tried live.
                $isPaid = $i > 2;

                foreach ([[$karim, 5.0], [$rahim, 7.0]] as [$partner, $rate]) {
                    $amount = round($profit * $rate / 100, 2);

                    CommissionSettlement::query()->create([
                        'user_id' => $partner->id,
                        'commission_period_id' => $period->id,
                        'period_start' => $start,
                        'period_end' => $end,
                        'business_profit' => $profit,
                        'commission_rate' => $rate,
                        'amount' => $amount,
                        'status' => $isPaid ? 'paid' : 'pending',
                    ]);

                    if ($isPaid) {
                        Payout::query()->create([
                            'user_id' => $partner->id,
                            'amount' => $amount,
                            'note' => 'Commission payout — '.$partner->name,
                            'payout_date' => $end,
                        ]);
                    }
                }
            }
        }

        // Pending entries for the current period (owner confirmation demo).
        Expense::query()->create([
            'user_id' => $karim->id,
            'expense_head_id' => $transportId,
            'amount' => 450,
            'note' => 'Demo expense',
            'entry_date' => now()->subDay(),
            'status' => EntryStatus::Pending,
        ]);

        Purchase::query()->create([
            'user_id' => $rahim->id,
            'purchase_item_id' => $cottonId,
            'quantity' => 20,
            'unit_price' => 150,
            'total' => 3000,
            'note' => 'Demo purchase',
            'entry_date' => now()->subDay(),
            'status' => EntryStatus::Pending,
        ]);
    }
}
