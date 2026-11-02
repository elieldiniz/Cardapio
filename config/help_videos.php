<?php

/*
 * Central de ajuda em vídeo (RF-24). Cada vídeo tem `url` (arquivo MP4 em public/videos/ajuda, ou um link externo); enquanto estiver
 * nulo, a tela mostra "Vídeo em breve". `tags` alimentam a busca. Para adicionar um vídeo, basta um item novo.
 */
return [
    'support_whatsapp' => env('SUPPORT_WHATSAPP'),

    'videos' => [
        ['title' => 'Primeiros passos no painel', 'description' => 'Do cadastro ao primeiro pedido em poucos minutos.', 'tags' => ['início', 'começar', 'cadastro', 'guia'], 'url' => '/videos/ajuda/primeiros-passos.mp4'],
        ['title' => 'Como gerar o vídeo de um produto', 'description' => 'Envie a foto, escolha o estilo e aprove o vídeo.', 'tags' => ['vídeo', 'foto', 'produto', 'gerar', 'estilo'], 'url' => '/videos/ajuda/gerar-video.mp4'],
        ['title' => 'Cadastrar produtos, complementos e pizzas', 'description' => 'Preço, promoção, adicionais, tamanhos e sabores.', 'tags' => ['produto', 'complemento', 'pizza', 'preço', 'promoção', 'kg'], 'url' => '/videos/ajuda/cadastrar-produto.mp4'],
        ['title' => 'Configurar horário, Pix e entrega', 'description' => 'Aberto/fechado automático, chave Pix e taxa de entrega.', 'tags' => ['horário', 'pix', 'entrega', 'taxa', 'bairro', 'distância', 'configurações'], 'url' => '/videos/ajuda/configuracoes.mp4'],
        ['title' => 'Receber e acompanhar pedidos', 'description' => 'Aceitar, preparar, mandar para entrega e finalizar.', 'tags' => ['pedido', 'pedidos', 'gestor', 'aceitar', 'status', 'whatsapp'], 'url' => '/videos/ajuda/pedidos.mp4'],
        ['title' => 'Abrir e fechar o caixa', 'description' => 'Troco inicial, sangria, reforço e fechamento.', 'tags' => ['caixa', 'sangria', 'reforço', 'fechamento', 'dinheiro'], 'url' => '/videos/ajuda/caixa.mp4'],
        ['title' => 'Vender no balcão (PDV) e fiado', 'description' => 'Venda rápida, pagamento dividido e cobrança de fiado.', 'tags' => ['pdv', 'balcão', 'fiado', 'venda', 'pagamento'], 'url' => '/videos/ajuda/pdv-fiado.mp4'],
        ['title' => 'Criar um cupom de desconto', 'description' => 'Crie um código, defina o desconto e copie o link para divulgar.', 'tags' => ['cupom', 'desconto', 'código', 'link', 'divulgar', 'instagram'], 'url' => '/videos/ajuda/cupons.mp4'],
        ['title' => 'Cartão fidelidade', 'description' => 'Configure o programa de selos e entregue o prêmio.', 'tags' => ['fidelidade', 'selo', 'prêmio', 'clientes', 'cartão'], 'url' => '/videos/ajuda/fidelidade.mp4'],
        ['title' => 'Motoboys e comanda de impressão', 'description' => 'Link do entregador, acerto e impressão 58/80 mm.', 'tags' => ['motoboy', 'entregador', 'comanda', 'impressora', 'acerto'], 'url' => '/videos/ajuda/motoboys.mp4'],
        ['title' => 'QR Code e divulgação', 'description' => 'Display de mesa e links por canal para medir vendas.', 'tags' => ['qr', 'divulgação', 'instagram', 'campanha', 'origem'], 'url' => '/videos/ajuda/qrcode.mp4'],
    ],
];
