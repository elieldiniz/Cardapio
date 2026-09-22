<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * The dono panel's information architecture, as laid out in the
 * "Painel do Restaurante" mockup.
 */
class PanelNavigation
{
    /**
     * @return array<int, array{label: string, route: string, icon: string}>
     */
    public static function definitions(): array
    {
        return [
            ['label' => 'Início', 'route' => 'panel.home', 'icon' => 'home'],
            ['label' => 'Categorias', 'route' => 'panel.categories', 'icon' => 'categories'],
            ['label' => 'Pratos', 'route' => 'panel.dishes', 'icon' => 'dishes'],
            ['label' => 'Aparência', 'route' => 'panel.appearance', 'icon' => 'appearance'],
            ['label' => 'QR Code', 'route' => 'panel.qr-code', 'icon' => 'qr'],
            ['label' => 'Visualizações', 'route' => 'panel.views', 'icon' => 'views'],
            ['label' => 'Assinatura', 'route' => 'panel.subscription', 'icon' => 'subscription'],
        ];
    }

    /**
     * Navigation entries resolved against the current request. An entry whose
     * screen is not registered yet has a null url and renders disabled.
     *
     * @return array<int, array{label: string, route: string, icon: string, url: ?string, active: bool}>
     */
    public static function items(): array
    {
        return array_map(fn (array $item) => $item + [
            'url' => Route::has($item['route']) ? route($item['route']) : null,
            'active' => request()->routeIs($item['route'], $item['route'].'.*'),
        ], static::definitions());
    }
}
