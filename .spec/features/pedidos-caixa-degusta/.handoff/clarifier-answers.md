# Respostas do desenvolvedor (rodada de esclarecimento)

Todas as respostas adotam o default recomendado pelo clarifier.

- **Q-01 Fuso (RNF-09):** um fuso por loja, padrão `America/Porto_Velho`, editável em Configurações. O app segue em UTC; a conversão acontece na camada de apresentação e nas regras de horário (aberto/fechado, caixa, "hoje/ontem", horário de categoria).
- **Q-02 Plano (RF-21):** contadores simples (pedidos, vendas, origem) em todos os planos. Funil, rankings (mais vistos/adicionados/pedidos, complementos mais escolhidos) e horário de pico só nos planos pagos mais altos, usando o nível de métricas que o plano já tem (`metrics_levels`). O plano deve nomear o corte concreto a partir dos dados de `PlanSeeder`/`MetricsLevelSeeder`.
- **Q-03 Privacidade (RNF-08):** cumprir o aviso. Comando agendado apaga endereço e observações 30 dias após o pedido ser finalizado ou recusado. Nome, WhatsApp e valores permanecem até 2 anos ou "Apagar dados". Pedidos antigos deixam de exibir o endereço.
- **Q-04 Caixa (RF-09/RF-10):** o "esperado na gaveta" conta só dinheiro; Pix e cartão aparecem à parte no relatório. Com o caixa fechado o dono PODE finalizar pedido de delivery, mas o dinheiro só vira movimento de caixa se houver caixa aberto, com aviso na tela. O PDV continua exigindo caixa aberto (RF-13).
- **Q-05 Cancelamento (RF-08/RF-17/RF-18):** é permitido cancelar pedido finalizado, só com motivo. Gera movimento de estorno no caixa (nada é apagado), devolve o selo de fidelidade e o uso do cupom, e cancela o fiado se ainda não foi pago. Definir a transição `finalizado → cancelado` além da recusa.
- **Q-06 Entrega, mínimo e Pix (RF-06b/RF-06c):** (a) bairro fora da lista pode pedir entrega com taxa "a combinar", e o dono define a taxa ao aceitar; (b) o pedido mínimo vale para o subtotal antes do desconto/cupom; (c) o Pix nunca marca o pedido como pago sozinho: o dono confere no app do banco e confirma ao finalizar.
- **Q-07 Links públicos (CT-03, CT-05, RF-22):** a página `/p/{token}` mostra só o primeiro nome e endereço parcial e expira 7 dias após o fim do pedido. O link do motoboy pode ser revogado pelo dono e mostra só os pedidos em rota do dia. A mensagem do WhatsApp leva o mesmo link, então a regra vale para ela.
- **Q-08 Durante o pedido (RF-02/RF-06e):** se um produto ficar indisponível ou a loja fechar entre montar o carrinho e confirmar, o servidor recusa com mensagem clara dizendo qual item saiu e o cliente edita o carrinho. Pedido feito com "aceitar pedido fechado" entra como novo, com o selo "feito fora do horário".

## Observações menores do clarifier para a SPEC (sem pergunta ao dono)
- RF-02d e RF-24 reúnem muitos itens num RF só (guia, ajuda, PWA, landing, taxa por distância); separar ou reforçar os critérios de aceite, em especial a regra da taxa por distância.
- CT-01 ("rota a confirmar") e CT-06 ("nomes ilustrativos") devem ser fechados no plano.
- RF-23 ("guardadas por aparelho") precisa definir onde a opção é armazenada.
