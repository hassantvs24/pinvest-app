<?php

/**
 * Bangla validation messages (subset used by this app).
 */
return [
    'required' => ':attribute অবশ্যই দিতে হবে।',
    'numeric' => ':attribute অবশ্যই সংখ্যা হতে হবে।',
    'integer' => ':attribute অবশ্যই পূর্ণসংখ্যা হতে হবে।',
    'min' => [
        'numeric' => ':attribute কমপক্ষে :min হতে হবে।',
        'string' => ':attribute কমপক্ষে :min অক্ষর হতে হবে।',
    ],
    'max' => [
        'numeric' => ':attribute সর্বোচ্চ :max হতে পারে।',
        'string' => ':attribute সর্বোচ্চ :max অক্ষর হতে পারে।',
    ],
    'exists' => 'নির্বাচিত :attribute সঠিক নয়।',
    'unique' => 'এই :attribute আগে থেকেই ব্যবহার হয়েছে।',
    'confirmed' => ':attribute মিলছে না।',
    'email' => ':attribute সঠিক ইমেইল ঠিকানা হতে হবে।',
    'date' => ':attribute সঠিক তারিখ হতে হবে।',
    'in' => 'নির্বাচিত :attribute সঠিক নয়।',
    'boolean' => ':attribute সঠিক নয়।',

    'attributes' => [
        'identifier' => 'ইমেইল অথবা মোবাইল নম্বর',
        'name' => 'নাম',
        'email' => 'ইমেইল',
        'phone' => 'মোবাইল নম্বর',
        'password' => 'পাসওয়ার্ড',
        'language' => 'ভাষা',
        'head_id' => 'আইটেম',
        'amount' => 'টাকার পরিমাণ',
        'quantity' => 'পরিমাণ',
        'unit_price' => 'একক দাম',
        'total' => 'মোট',
        'entry_date' => 'তারিখ',
        'invested_at' => 'তারিখ',
        'payout_date' => 'তারিখ',
        'note' => 'নোট',
        'default_price' => 'ডিফল্ট দাম',
        'commission_rate' => 'কমিশন হার',
        'user_id' => 'পার্টনার',
        'is_active' => 'স্ট্যাটাস',
    ],
];
