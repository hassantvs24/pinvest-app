<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inventory unit conversion
    |--------------------------------------------------------------------------
    |
    | Grams per unit for the weight unit family (kg / gram / tola). The
    | tola weight is configurable because gold/silver tola weight varies
    | by market convention (Bangladesh gold tola = 11.664 g).
    |
    */

    'grams_per_unit' => [
        'kg' => 1000.0,
        'gram' => 1.0,
        'tola' => (float) env('INVENTORY_TOLA_GRAMS', 11.664),
    ],

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | Interface languages offered by the language picker. The first one
    | is the app default.
    |
    */

    'languages' => ['bn', 'en'],
];
