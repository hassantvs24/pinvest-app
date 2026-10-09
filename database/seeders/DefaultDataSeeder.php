<?php

namespace Database\Seeders;

use App\Enums\ExpenseCostType;
use App\Models\ExpenseHead;
use App\Models\Item;
use App\Models\RegistrationAllow;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;

/**
 * Permanent business defaults in Bangla — always safe to re-run
 * (firstOrCreate everywhere). Runs on every `db:seed`, so even after
 * flushing demo data the agar/oud business can start immediately:
 * owner + partner accounts, expense heads, purchase/sale items with
 * correct units and resale links.
 */
class DefaultDataSeeder extends Seeder
{
    public function run(): void
    {
        // Owner account — required to log in at all.
        User::query()->firstOrCreate(
            ['phone' => '01675870047'],
            [
                'name' => 'Nazmul',
                'email' => null,
                'password' => '123456',
                'role' => UserRole::Owner,
                'commission_rate' => 0,
                'preferred_language' => 'bn',
                'is_active' => true,
            ],
        );

        // Real partners (default password 123456 — they can change it in
        // their profile). Commission rate stays 0 until the owner sets it
        // from the partners page.
        foreach ([
            ['name' => 'Raju', 'phone' => '01747666533'],
            ['name' => 'Sahel', 'phone' => '01705752545'],
            ['name' => 'Riad', 'phone' => '01641196743'],
        ] as $partner) {
            User::query()->firstOrCreate(
                ['phone' => $partner['phone']],
                [
                    'name' => $partner['name'],
                    'email' => null,
                    'password' => '123456',
                    'role' => UserRole::Partner,
                    'commission_rate' => 0,
                    'preferred_language' => 'bn',
                    'is_active' => true,
                ],
            );

            RegistrationAllow::query()->firstOrCreate(['phone' => $partner['phone']], ['used_at' => now()]);
        }

        // Expense heads: product costs join stock (COGS when sold),
        // general costs hit profit directly.
        foreach ([
            ['পরিবহন ভাড়া', ExpenseCostType::Product],
            ['গাছ কাটা মজুরি', ExpenseCostType::Product],
            ['প্রসেসিং মজুরি', ExpenseCostType::Product],
            ['জাসাই-বাসাই খরচ', ExpenseCostType::Product],
            ['সোর্সিং ফি', ExpenseCostType::Product],
            ['প্যাকেজিং', ExpenseCostType::Product],
            ['মার্কেটিং', ExpenseCostType::Product],
            ['দোকান ভাড়া', ExpenseCostType::General],
            ['বিদ্যুৎ বিল', ExpenseCostType::General],
            ['অন্যান্য', ExpenseCostType::General],
        ] as [$name, $costType]) {
            ExpenseHead::query()->firstOrCreate(
                ['name' => $name],
                ['cost_type' => $costType, 'is_active' => true],
            );
        }

        // Unified item list: the same items are bought, sold and used
        // in production — one weighted-average pool per item.
        foreach ([
            ['name' => 'অগর গাছ', 'unit' => 'pcs', 'default_price' => 0],
            ['name' => 'উদ চিপস', 'unit' => 'gram', 'default_price' => 6000],
            ['name' => 'আসারি', 'unit' => 'gram', 'default_price' => 300],
            ['name' => 'উদ অয়েল', 'unit' => 'ml', 'default_price' => 15000],
        ] as $item) {
            Item::query()->firstOrCreate(
                ['name' => $item['name']],
                ['unit' => $item['unit'], 'default_price' => $item['default_price'], 'is_active' => true],
            );
        }
    }
}
