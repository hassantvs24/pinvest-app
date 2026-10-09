<?php

/**
 * English validation messages (subset used by this app).
 */
return [
    'required' => 'The :attribute field is required.',
    'numeric' => 'The :attribute field must be a number.',
    'integer' => 'The :attribute field must be an integer.',
    'min' => [
        'numeric' => 'The :attribute field must be at least :min.',
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'max' => [
        'numeric' => 'The :attribute field must not exceed :max.',
        'string' => 'The :attribute field must not exceed :max characters.',
    ],
    'exists' => 'The selected :attribute is invalid.',
    'unique' => 'The :attribute has already been taken.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'email' => 'The :attribute field must be a valid email address.',
    'date' => 'The :attribute field must be a valid date.',
    'in' => 'The selected :attribute is invalid.',
    'boolean' => 'The :attribute field must be true or false.',

    'attributes' => [
        'identifier' => 'email or mobile number',
        'name' => 'name',
        'email' => 'email',
        'phone' => 'mobile number',
        'password' => 'password',
        'language' => 'language',
        'head_id' => 'item',
        'amount' => 'amount',
        'quantity' => 'quantity',
        'unit_price' => 'unit price',
        'total' => 'total',
        'entry_date' => 'date',
        'invested_at' => 'date',
        'payout_date' => 'date',
        'note' => 'note',
        'default_price' => 'default price',
        'commission_rate' => 'commission rate',
        'user_id' => 'partner',
        'is_active' => 'status',
    ],
];
