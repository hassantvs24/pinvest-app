<?php

namespace Database\Seeders;

use App\EntryStatus;
use App\Models\CommissionPeriod;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\Investment;
use App\Models\Payout;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\CommissionSettlementService;
use Illuminate\Database\Seeder;

/**
 * Demo data: ten months of closed commission cycles plus a demo open
 * cycle with pending entries. Only runs on an EMPTY database (no
 * commission periods) so re-seeding a real business never pollutes it.
 * Masters and accounts come from DefaultDataSeeder.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (CommissionPeriod::query()->exists()) {
            return;
        }

        $owner = User::query()->where('phone', '01675870047')->firstOrFail();

        // Demo commission rates so settlements can be seen in the demo.
        $raju = User::query()->where('phone', '01747666533')->firstOrFail();
        $sahel = User::query()->where('phone', '01705752545')->firstOrFail();
        $riad = User::query()->where('phone', '01641196743')->firstOrFail();
        $raju->update(['commission_rate' => 5]);
        $sahel->update(['commission_rate' => 7]);
        $riad->update(['commission_rate' => 10]);

        Investment::query()->create([
            'amount' => 100000,
            'note' => 'প্রাথমিক মূলধন',
            'invested_at' => now()->subMonths(11)->startOfMonth(),
        ]);

        $woodId = PurchaseItem::query()->where('name', 'অগর গাছ')->value('id');
        $oilId = PurchaseItem::query()->where('name', 'উদ অয়েল')->value('id');
        $premiumId = SaleItem::query()->where('name', 'উদ চিপস — প্রিমিয়াম')->value('id');
        $standardId = SaleItem::query()->where('name', 'উদ চিপস — স্ট্যান্ডার্ড')->value('id');
        $scrapId = SaleItem::query()->where('name', 'আসারি')->value('id');
        $gradeAId = SaleItem::query()->where('name', 'উদ অয়েল — গ্রেড A')->value('id');
        $transportId = ExpenseHead::query()->where('name', 'পরিবহন ভাড়া')->value('id');
        $labourId = ExpenseHead::query()->where('name', 'প্রসেসিং মজুরি')->value('id');

        // Ten closed commission cycles (last 10 full months). Month
        // index 4 is a deliberate LOSS month to demo the loss state.
        for ($i = 10; $i >= 1; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = now()->subMonths($i)->endOfMonth();
            $mid = $start->copy()->addDays(14);

            $period = CommissionPeriod::query()->create([
                'label' => $start->format('F Y'),
                'opened_at' => $start,
                'opening_cash' => $i === 10 ? 100000 : null,
                'status' => 'open',
            ]);

            if ($i === 4) {
                // Loss month: sales below expenses.
                Sale::query()->create([
                    'user_id' => $raju->id,
                    'sale_item_id' => $gradeAId,
                    'quantity' => 1,
                    'unit_price' => 15000,
                    'total' => 15000,
                    'note' => 'মন্দা মাস',
                    'entry_date' => $mid,
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid->copy()->addDay(),
                ]);
                Expense::query()->create([
                    'user_id' => $raju->id,
                    'expense_head_id' => $transportId,
                    'amount' => 20000,
                    'note' => 'গাছ সংগ্রহ ট্রিপ',
                    'entry_date' => $mid->copy()->subDay(),
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid,
                ]);
            } else {
                // Regular month: buy wood, process into chips + scrap,
                // sell chips; oil is resold directly.
                Purchase::query()->create([
                    'user_id' => $sahel->id,
                    'purchase_item_id' => $woodId,
                    'quantity' => 15 + $i,
                    'unit_price' => 400,
                    'total' => (15 + $i) * 400,
                    'note' => 'গাছের লট',
                    'entry_date' => $mid->copy()->subDay(),
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid,
                ]);
                Purchase::query()->create([
                    'user_id' => $sahel->id,
                    'purchase_item_id' => $oilId,
                    'quantity' => 2,
                    'unit_price' => 11000,
                    'total' => 22000,
                    'note' => 'অয়েলের লট',
                    'entry_date' => $mid->copy()->subDay(),
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid,
                ]);

                $production = Production::query()->create([
                    'user_id' => $owner->id,
                    'extra_cost' => 3000,
                    'note' => 'কাটাই + জাসাই-বাসাই',
                    'entry_date' => $mid,
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid,
                ]);
                $production->components()->create(['purchase_item_id' => $woodId, 'quantity' => 15 + $i]);
                $production->outputs()->create(['sale_item_id' => $premiumId, 'quantity' => 40 + $i]);
                $production->outputs()->create(['sale_item_id' => $standardId, 'quantity' => 60 + $i]);
                $production->outputs()->create(['sale_item_id' => $scrapId, 'quantity' => 20]);

                Sale::query()->create([
                    'user_id' => $raju->id,
                    'sale_item_id' => $premiumId,
                    'quantity' => 30,
                    'unit_price' => 12000,
                    'total' => 360000,
                    'note' => 'প্রিমিয়াম চিপস',
                    'entry_date' => $mid->copy()->addDay(),
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid->copy()->addDays(2),
                ]);
                Sale::query()->create([
                    'user_id' => $raju->id,
                    'sale_item_id' => $standardId,
                    'quantity' => 40,
                    'unit_price' => 6000,
                    'total' => 240000,
                    'note' => 'স্ট্যান্ডার্ড চিপস',
                    'entry_date' => $mid->copy()->addDays(2),
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid->copy()->addDays(3),
                ]);
                Sale::query()->create([
                    'user_id' => $riad->id,
                    'sale_item_id' => $gradeAId,
                    'quantity' => 1,
                    'unit_price' => 15000,
                    'total' => 15000,
                    'note' => 'অয়েল পুনবিক্রি',
                    'entry_date' => $mid->copy()->addDays(2),
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid->copy()->addDays(3),
                ]);
                Expense::query()->create([
                    'user_id' => $raju->id,
                    'expense_head_id' => $transportId,
                    'amount' => 800 + $i * 50,
                    'note' => 'গাছ সংগ্রহ ট্রিপ',
                    'entry_date' => $mid,
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid->copy()->addDay(),
                ]);
                Expense::query()->create([
                    'user_id' => $riad->id,
                    'expense_head_id' => $labourId,
                    'amount' => 500,
                    'note' => 'কাটার মজুরি',
                    'entry_date' => $mid,
                    'status' => EntryStatus::Confirmed,
                    'confirmed_by' => $owner->id,
                    'confirmed_at' => $mid->copy()->addDay(),
                ]);
            }

            // Close through the real service so profit/settlements are
            // computed with the same COGS logic the app uses live.
            $result = CommissionSettlementService::closePeriod($period, $end);

            // Older cycles are paid out; the two most recent stay pending
            // so the payout-request demo flow can be tried live.
            if ($result['profit'] > 0 && $i > 2) {
                $settlements = CommissionSettlement::query()
                    ->where('commission_period_id', $period->id)
                    ->get();

                foreach ($settlements as $settlement) {
                    $settlement->update(['status' => 'paid']);

                    Payout::query()->create([
                        'user_id' => $settlement->user_id,
                        'amount' => $settlement->amount,
                        'note' => 'কমিশন উত্তোলন — '.$period->label,
                        'payout_date' => $end,
                    ]);
                }
            }
        }

        // Demo cycle: leave one cycle OPEN so the pending demo entries
        // below belong to it (in real usage entries cannot exist without
        // an open cycle — the seeder must not show an impossible state).
        CommissionPeriod::query()->create([
            'label' => 'Demo',
            'opened_at' => now()->subDays(3),
            'status' => 'open',
        ]);

        // Pending entries for the current period (owner confirmation demo).
        Expense::query()->create([
            'user_id' => $raju->id,
            'expense_head_id' => $transportId,
            'amount' => 450,
            'note' => 'ডেমো খরচ',
            'entry_date' => now()->subDay(),
            'status' => EntryStatus::Pending,
        ]);

        Purchase::query()->create([
            'user_id' => $sahel->id,
            'purchase_item_id' => $woodId,
            'quantity' => 3,
            'unit_price' => 400,
            'total' => 1200,
            'note' => 'ডেমো ক্রয়',
            'entry_date' => now()->subDay(),
            'status' => EntryStatus::Pending,
        ]);
    }
}
