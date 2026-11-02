<?php

/*
|--------------------------------------------------------------------------
| Ready-made complement groups (RF-03)
|--------------------------------------------------------------------------
|
| Starting points for the "Complementos" screen. Prices are examples the dono
| adjusts. `mode`: "one" = pick a single option, "many" = pick several up to
| `max_choices`.
|
*/

return [
    'tamanhos' => [
        'label' => 'Tamanhos',
        'description' => 'P, M, G — cada um com seu preço',
        'icon' => '📏',
        'mode' => 'one', 'max_choices' => 1, 'is_required' => true, 'allow_repeat' => false,
        'options' => [['name' => 'Pequeno', 'price' => null], ['name' => 'Médio', 'price' => '5.00'], ['name' => 'Grande', 'price' => '10.00']],
    ],
    'adicionais' => [
        'label' => 'Adicionais',
        'description' => 'Bacon, cheddar, ovo… pode repetir',
        'icon' => '🥓',
        'mode' => 'many', 'max_choices' => 5, 'is_required' => false, 'allow_repeat' => true,
        'options' => [['name' => 'Bacon', 'price' => '4.00'], ['name' => 'Cheddar', 'price' => '3.00'], ['name' => 'Ovo', 'price' => '2.00'], ['name' => 'Hambúrguer extra', 'price' => '6.00']],
    ],
    'bordas' => [
        'label' => 'Bordas',
        'description' => 'Catupiry, cheddar, chocolate',
        'icon' => '🍕',
        'mode' => 'one', 'max_choices' => 1, 'is_required' => false, 'allow_repeat' => false,
        'options' => [['name' => 'Catupiry', 'price' => '8.00'], ['name' => 'Cheddar', 'price' => '8.00'], ['name' => 'Chocolate', 'price' => '10.00']],
    ],
    'bebida-combo' => [
        'label' => 'Bebida do combo',
        'description' => 'Obrigatório escolher 1',
        'icon' => '🥤',
        'mode' => 'one', 'max_choices' => 1, 'is_required' => true, 'allow_repeat' => false,
        'options' => [['name' => 'Coca-Cola lata', 'price' => null], ['name' => 'Guaraná lata', 'price' => null], ['name' => 'Suco natural', 'price' => '3.00']],
    ],
    'molhos' => [
        'label' => 'Molhos',
        'description' => 'Até 3, grátis',
        'icon' => '🫙',
        'mode' => 'many', 'max_choices' => 3, 'is_required' => false, 'allow_repeat' => false,
        'options' => [['name' => 'Maionese da casa', 'price' => null], ['name' => 'Barbecue', 'price' => null], ['name' => 'Ketchup', 'price' => null], ['name' => 'Mostarda', 'price' => null]],
    ],
    'ponto-carne' => [
        'label' => 'Ponto da carne',
        'description' => 'Mal passado, ao ponto, bem passado',
        'icon' => '🔥',
        'mode' => 'one', 'max_choices' => 1, 'is_required' => true, 'allow_repeat' => false,
        'options' => [['name' => 'Mal passado', 'price' => null], ['name' => 'Ao ponto', 'price' => null], ['name' => 'Bem passado', 'price' => null]],
    ],
    'tipo-corte' => [
        'label' => 'Tipo de corte',
        'description' => 'Para açougues',
        'icon' => '🔪',
        'mode' => 'one', 'max_choices' => 1, 'is_required' => false, 'allow_repeat' => false,
        'options' => [['name' => 'Inteiro', 'price' => null], ['name' => 'Em bifes', 'price' => null], ['name' => 'Em cubos', 'price' => null], ['name' => 'Moído', 'price' => null]],
    ],
    'acai' => [
        'label' => 'Complementos de açaí',
        'description' => 'Granola, frutas, cremes',
        'icon' => '🍇',
        'mode' => 'many', 'max_choices' => 4, 'is_required' => false, 'allow_repeat' => false,
        'options' => [['name' => 'Granola', 'price' => null], ['name' => 'Banana', 'price' => null], ['name' => 'Leite condensado', 'price' => '2.00'], ['name' => 'Morango', 'price' => '3.00'], ['name' => 'Paçoca', 'price' => '2.00']],
    ],
    'zero' => [
        'label' => 'Começar do zero',
        'description' => 'Monte do seu jeito',
        'icon' => '➕',
        'mode' => 'one', 'max_choices' => 1, 'is_required' => false, 'allow_repeat' => false,
        'options' => [],
    ],
];
