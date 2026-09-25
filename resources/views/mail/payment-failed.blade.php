<x-mail::message>
<x-slot:preheader>Seu cardápio segue no ar, mas o cartão precisa de atenção nos próximos dias.</x-slot:preheader>
<x-slot:hero>
<x-mail::hero eyebrow="Ação necessária" tone="danger" title="Não conseguimos cobrar sua assinatura.">
Seu cardápio continua no ar por enquanto. Atualize o cartão para não perder nenhum prato.
</x-mail::hero>
</x-slot:hero>

Olá, **{{ $name }}**!

A cobrança da assinatura{{ $restaurant ? ' do **'.$restaurant.'**' : '' }} foi recusada no cartão cadastrado. Normalmente o motivo é cartão vencido, falta de limite ou bloqueio do banco.

<x-mail::callout tone="danger" title="O que acontece agora">
Vamos tentar cobrar de novo nos próximos dias. Se todas as tentativas falharem, o restaurante volta ao **plano Grátis** e os pratos acima do limite deixam de aparecer para os clientes.
</x-mail::callout>

<x-mail::button :url="$url" color="error">
Atualizar cartão agora
</x-mail::button>

Leva menos de um minuto. Já resolveu? Então pode ignorar este aviso.

<x-slot:subcopy>
Se o botão não funcionar, copie e cole este endereço no navegador: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
