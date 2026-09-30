<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Landing page
    |--------------------------------------------------------------------------
    |
    | Plans shown on the landing come from the database (edited in /admin), so
    | prices there always match checkout. Set LANDING_SHOW_PRICES=false to
    | show only "Comece grátis" while pricing is still being defined.
    |
    | Contact channels appear in the header/footer and on /contato only when set.
    |
    */

    'show_prices' => (bool) env('LANDING_SHOW_PRICES', true),

    'contact' => [
        'email' => env('LANDING_CONTACT_EMAIL'),
        'whatsapp' => preg_replace('/\D+/', '', (string) env('LANDING_CONTACT_WHATSAPP')) ?: null, // country code + DDD + number, e.g. 5569999999999 (non-digits are ignored)
        'instagram' => env('LANDING_CONTACT_INSTAGRAM'), // handle without @
    ],

    'legal_entity' => env('LANDING_LEGAL_ENTITY', env('APP_NAME', 'Degusta')),

];
