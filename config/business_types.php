<?php

/*
|--------------------------------------------------------------------------
| Business types offered at signup (RF-02c)
|--------------------------------------------------------------------------
|
| Choosing one makes the new restaurant start with a sensible set of
| categories (name + icon). Choosing none creates no category, as before.
|
*/

return [
    'lanchonete' => [
        'label' => 'Lanchonete / Hamburgueria',
        'categories' => [
            ['name' => 'Lanches', 'icon' => '🍔'],
            ['name' => 'Porções', 'icon' => '🍟'],
            ['name' => 'Bebidas', 'icon' => '🥤'],
            ['name' => 'Sobremesas', 'icon' => '🍨'],
        ],
    ],
    'pizzaria' => [
        'label' => 'Pizzaria',
        'categories' => [
            ['name' => 'Pizzas', 'icon' => '🍕'],
            ['name' => 'Bordas e adicionais', 'icon' => '🧀'],
            ['name' => 'Bebidas', 'icon' => '🥤'],
            ['name' => 'Sobremesas', 'icon' => '🍰'],
        ],
    ],
    'marmitaria' => [
        'label' => 'Restaurante / Marmitaria',
        'categories' => [
            ['name' => 'Pratos do dia', 'icon' => '🍛'],
            ['name' => 'Marmitas', 'icon' => '🍱'],
            ['name' => 'Bebidas', 'icon' => '🥤'],
            ['name' => 'Sobremesas', 'icon' => '🍮'],
        ],
    ],
    'acaiteria' => [
        'label' => 'Açaíteria / Sorveteria',
        'categories' => [
            ['name' => 'Açaí', 'icon' => '🍇'],
            ['name' => 'Sorvetes', 'icon' => '🍦'],
            ['name' => 'Adicionais', 'icon' => '🍓'],
            ['name' => 'Bebidas', 'icon' => '🥤'],
        ],
    ],
    'doceria' => [
        'label' => 'Doceria / Pastelaria',
        'categories' => [
            ['name' => 'Doces', 'icon' => '🍬'],
            ['name' => 'Bolos', 'icon' => '🎂'],
            ['name' => 'Salgados e pastéis', 'icon' => '🥟'],
            ['name' => 'Bebidas', 'icon' => '☕'],
        ],
    ],
];
