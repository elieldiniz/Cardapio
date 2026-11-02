# Input confirmado — pedidos-caixa-degusta

slug: pedidos-caixa-degusta
tier: complete (confirmado pelo desenvolvedor: uma SPEC guarda-chuva, fases 0 a 7 no PHASES.md)

## Resumo
Transformar o Degusta de cardápio em vídeo (somente visualização) em sistema de cardápio + pedidos + entrega + caixa, sem regredir o que já existe (feed em vídeo, aparência, QR, billing, suporte, admin Filament).

## Critérios de aceite confirmados (todos `drafted`, aprovados pelo desenvolvedor)
1. O dono configura WhatsApp, endereço, Pix e horário por dia; o feed mostra "Aberto agora" ou "Fechado · abre às X".
2. O cliente monta o pedido no feed (produto, complementos, observação) e o servidor recalcula todos os preços.
3. O checkout oferece retirada ou entrega, taxa por bairro, frete grátis, pedido mínimo e Pix, dinheiro com troco ou cartão.
4. O pedido é salvo com código único, gera a mensagem de WhatsApp pronta, pergunta "Sim, enviei" e tem página de acompanhamento `/p/{token}`.
5. O gestor de pedidos em colunas permite aceitar, recusar, avançar, avisar o cliente e toca som ao chegar pedido novo.
6. Ao finalizar, o pagamento é registrado como Pix, dinheiro (com troco), cartão, fiado ou dividido.
7. O caixa abre e fecha contando o dinheiro, aceita despesa, retirada e reforço; há fiado e relatório por período.
8. O PDV de balcão vende no mesmo caixa.
9. Existem pizza (tamanhos, sabores, meio a meio), venda por kg com ajuste de valor, promoção "de/por" e banner.
10. Existem cupons do restaurante, cartão fidelidade, clientes com "Apagar dados", origem dos pedidos e estatísticas de funil.
11. Existem motoboys com acerto, impressão de comanda, guia de primeiros passos, ajuda em vídeo, estilos de cardápio e landing atualizada.
12. Nada do que já existe regride: os testes atuais continuam passando.

## Decisões já tomadas pelo desenvolvedor
- Pedidos disponíveis em todos os planos (planos se diferenciam por vídeo, limite de pratos e métricas).
- Sem pagamento online dentro do Degusta nesta entrega: Pix direto para a conta do dono, conferido no app do banco.
- Rótulo "Pratos" vira "Produtos" no painel; `dishes` permanece no código.

## Fontes
- Descrição detalhada, modelo de dados rascunho e fases: `Docs/planejamento-pedidos-e-caixa.md` (seções 3, 3.1, 5, 6, 7, 8).
- Pontos do código atual a respeitar: o feed (`resources/views/feed/*`, `resources/js/feed.js`) é Blade + JS puro de propósito, sem Livewire; o painel é Livewire (`pages::panel.*`, `app/Support/PanelNavigation.php`); a tabela `coupons` é da cobrança da plataforma, então os cupons do restaurante usam outro nome; o worker roda com `--tries=1` e não há retry configurado.
- `docs/agents/project_overview.md` e o README afirmam que o produto é "só de visualização"; isso deve ser atualizado como tarefa.
