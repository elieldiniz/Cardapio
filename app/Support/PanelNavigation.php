<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * The dono panel's information architecture (UI-01): five groups, each with its
 * screens. Screens whose route is not registered yet are left out, and so are the
 * ones the restaurant's plan has switched off (RF-02d), so the menu never shows a
 * dead entry. The rows keep the "Painel do Restaurante" mockup look.
 */
class PanelNavigation
{
    /**
     * @return array<string, array<int, array{label: string, route: string, icon: string, feature?: string}>>
     */
    public static function groupDefinitions(): array
    {
        return [
            'Geral' => [
                ['label' => 'Início', 'route' => 'panel.home', 'icon' => 'home'],
                ['label' => 'Configurações', 'route' => 'panel.settings', 'icon' => 'settings'],
            ],
            'Cardápio' => [
                ['label' => 'Categorias', 'route' => 'panel.categories', 'icon' => 'categories'],
                ['label' => 'Produtos', 'route' => 'panel.dishes', 'icon' => 'dishes'],
                ['label' => 'Complementos', 'route' => 'panel.option-groups', 'icon' => 'layers'],
                ['label' => 'Aparência', 'route' => 'panel.appearance', 'icon' => 'appearance'],
            ],
            'Pedidos' => [
                ['label' => 'Pedidos', 'route' => 'panel.orders', 'icon' => 'orders', 'feature' => 'orders'],
                ['label' => 'PDV Balcão', 'route' => 'panel.pdv', 'icon' => 'pos', 'feature' => 'pdv'],
                ['label' => 'Caixa', 'route' => 'panel.cash', 'icon' => 'cash', 'feature' => 'cash'],
                ['label' => 'Fiado', 'route' => 'panel.receivables', 'icon' => 'receipt', 'feature' => 'cash'],
                ['label' => 'Relatório', 'route' => 'panel.report', 'icon' => 'chart', 'feature' => 'cash'],
                ['label' => 'Motoboys', 'route' => 'panel.couriers', 'icon' => 'bike', 'feature' => 'couriers'],
            ],
            'Crescimento' => [
                ['label' => 'Cupons', 'route' => 'panel.promo-codes', 'icon' => 'tag', 'feature' => 'promo_codes'],
                ['label' => 'Fidelidade', 'route' => 'panel.loyalty', 'icon' => 'star', 'feature' => 'loyalty'],
                ['label' => 'Clientes', 'route' => 'panel.customers', 'icon' => 'users', 'feature' => 'orders'],
                ['label' => 'Visualizações', 'route' => 'panel.views', 'icon' => 'views'],
                ['label' => 'QR Code', 'route' => 'panel.qr-code', 'icon' => 'qr'],
            ],
            'Conta' => [
                ['label' => 'Assinatura', 'route' => 'panel.subscription', 'icon' => 'subscription'],
                ['label' => 'Suporte', 'route' => 'panel.support', 'icon' => 'support'],
                ['label' => 'Ajuda', 'route' => 'panel.help', 'icon' => 'help'],
            ],
        ];
    }

    /**
     * Groups resolved against the current request and plan: only registered, permitted
     * screens remain, and a group with no screen left is dropped.
     *
     * @return array<int, array{label: string, items: array<int, array{label: string, route: string, icon: string, url: string, active: bool, badge: ?int}>}>
     */
    public static function groups(): array
    {
        $restaurant = auth()->user()?->restaurant;
        $groups = [];

        foreach (static::groupDefinitions() as $label => $items) {
            $resolved = [];

            foreach ($items as $item) {
                if (! Route::has($item['route'])) {
                    continue;
                }

                // Without the feature the item stays, marked with a lock, and leads to the "assine" page.
                $locked = isset($item['feature']) && $restaurant !== null && ! $restaurant->hasFeature($item['feature'])
            && ! ($item['feature'] === 'cash' && $restaurant->hasCashToWrapUp());

                $resolved[] = [
                    'label' => $item['label'],
                    'route' => $item['route'],
                    'icon' => $item['icon'],
                    'url' => $locked ? route('panel.locked', $item['feature']) : route($item['route']),
                    'locked' => $locked,
                    'active' => request()->routeIs($item['route'], $item['route'].'.*'),
                    'badge' => $item['route'] === 'panel.orders' ? static::newOrders() : null,
                ];
            }

            if ($resolved !== []) {
                $groups[] = ['label' => $label, 'items' => $resolved];
            }
        }

        return $groups;
    }

    /**
     * Orders waiting for the dono's first look: the red number on "Pedidos" (RF-08, UI-03).
     */
    private static function newOrders(): int
    {
        $restaurant = auth()->user()?->restaurant;

        return $restaurant === null ? 0 : $restaurant->orders()->whereHas('status', fn ($status) => $status->where('slug', 'novo'))->count();
    }

    /**
     * Every available entry in one flat list (kept for callers that need no grouping).
     *
     * @return array<int, array{label: string, route: string, icon: string, url: string, active: bool}>
     */
    public static function items(): array
    {
        return array_merge(...array_column(static::groups(), 'items'));
    }
}
