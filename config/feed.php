<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Appearance options (US-6.1)
    |--------------------------------------------------------------------------
    |
    | Accent swatches come from the "Painel do Restaurante" mockup; any hex
    | color is also accepted through the color picker.
    |
    | Fonts style the feed's titles (restaurant and dish names). Keys are the
    | CSS family names stored in restaurants.font; values are the
    | fonts.bunny.net family slug and weights loaded on the feed.
    |
    */

    'accent_swatches' => ['#FF6B3D', '#2E9E6B', '#3E7BFA', '#D94F8C'],

    'default_font' => 'DM Serif Display',

    /*
    | Visual styles of the cardápio's surfaces (grid, sheets, cart, checkout and tracking page).
    | The vertical video feed itself stays dark in every style.
    */
    'styles' => [
        'vidro' => ['label' => 'Vidro', 'hint' => 'Escuro com vidro fosco (o clássico).'],
        'claro' => ['label' => 'Claro', 'hint' => 'Fundo claro e limpo, bom para cardápios coloridos.'],
        'rustico' => ['label' => 'Rústico', 'hint' => 'Tons de papel e madeira, com cara de casa de bairro.'],
    ],

    'fonts' => [
        'DM Serif Display' => null, // bundled with the app
        'Playfair Display' => 'playfair-display:400,700',
        'Lora' => 'lora:400,700',
        'Bebas Neue' => 'bebas-neue:400',
        'Poppins' => 'poppins:500,700',
        'Montserrat' => 'montserrat:500,700',
    ],

];
