<?php

namespace App\Services\Panel;

use App\Models\Restaurant;

/**
 * The 9-step setup guide of the home screen (RF-24). Every step is derived from data that already
 * exists, so nothing is stored and a step ticks itself as soon as the work is done.
 */
class OnboardingProgress
{
    /**
     * @return array{steps: array<int, array{key: string, label: string, hint: string, route: string, done: bool}>, done: int, total: int, percent: int, next: ?array}
     */
    public function for(Restaurant $restaurant): array
    {
        $settings = $restaurant->orderSettings();

        $defs = [
            ['whatsapp', 'Informar o WhatsApp da loja', 'É para onde chegam os pedidos.', 'panel.settings', filled($settings->whatsapp_number)],
            ['hours', 'Definir o horário de funcionamento', 'O cardápio mostra "Aberto agora" sozinho.', 'panel.settings', $restaurant->businessHours()->exists()],
            ['category', 'Criar uma categoria', 'Ex.: Burgers, Bebidas, Sobremesas.', 'panel.categories', $restaurant->categories()->exists()],
            ['dish', 'Cadastrar o primeiro produto', 'Nome, preço e foto.', 'panel.dishes.create', $restaurant->dishes()->exists()],
            ['video', 'Gerar o vídeo de um produto', 'O vídeo é o que faz o cliente pedir.', 'panel.dishes', $restaurant->dishes()->whereNotNull('active_video_id')->exists()],
            ['pix', 'Cadastrar a chave Pix', 'O cliente paga na hora, sem taxa.', 'panel.settings', filled($settings->pix_key)],
            ['delivery', 'Configurar a entrega', 'Taxa por bairro, distância ou combinada.', 'panel.settings', $settings->delivery_mode !== 'a_combinar' || $settings->min_order_delivery !== null || $settings->free_delivery_over !== null],
            ['share', 'Divulgar o link ou o QR Code', 'Imprima o display da mesa ou poste no Instagram.', 'panel.qr-code', $restaurant->dishViews()->exists()],
            ['order', 'Receber o primeiro pedido', 'Faça um pedido de teste pelo seu próprio cardápio.', 'panel.orders', $restaurant->orders()->exists()],
        ];

        $steps = collect($defs)->map(fn (array $def) => [
            'key' => $def[0], 'label' => $def[1], 'hint' => $def[2], 'route' => $def[3], 'done' => (bool) $def[4],
        ])->all();

        $done = count(array_filter($steps, fn (array $step) => $step['done']));
        $total = count($steps);

        return [
            'steps' => $steps,
            'done' => $done,
            'total' => $total,
            'percent' => (int) round($done / $total * 100),
            'next' => collect($steps)->firstWhere('done', false),
        ];
    }
}
