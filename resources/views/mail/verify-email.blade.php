<x-mail::message>
<x-slot:preheader>{{ $newAccount ? 'Um clique e o painel do seu restaurante fica liberado.' : 'Confirme o novo endereço para continuar usando o painel.' }}</x-slot:preheader>
<x-slot:hero>
@if ($newAccount)
<x-mail::hero eyebrow="Boas-vindas" title="Seu cardápio em vídeo começa aqui.">
Falta só confirmar seu e-mail para liberar o painel{{ $restaurant ? ' do '.$restaurant : '' }}.
</x-mail::hero>
@else
<x-mail::hero eyebrow="Segurança da conta" title="Confirme seu novo e-mail.">
Você trocou o e-mail da sua conta. Confirme que este endereço é seu para continuar usando o painel.
</x-mail::hero>
@endif
</x-slot:hero>

Olá, **{{ $name }}**!

@if ($newAccount)
Que bom ter você por aqui. Confirme que este e-mail é seu e o painel já abre pronto para o primeiro prato.
@else
Assim que você confirmar, os avisos de vídeos e cobrança passam a chegar aqui.
@endif

<x-mail::button :url="$url">
Confirmar meu e-mail
</x-mail::button>

@if ($newAccount)
## O que vem depois

<x-mail::steps :items="[
    'Cadastre seus pratos' => 'Nome, preço e uma boa foto. Nada de gravar vídeo.',
    'A IA faz o vídeo' => 'Cada foto vira um vídeo curto, e você escolhe a melhor versão.',
    'Coloque o QR Code na mesa' => 'O cliente escaneia e vê cada prato rolando, como nos Reels.',
]" />
@endif

<x-mail::callout tone="neutral">
O link vale por **{{ $minutes }} minutos**. Não foi você? Pode ignorar este e-mail: nenhuma conta é liberada sem essa confirmação.
</x-mail::callout>

<x-slot:subcopy>
Se o botão não funcionar, copie e cole este endereço no navegador: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
