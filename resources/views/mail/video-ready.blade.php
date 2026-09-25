<x-mail::message>
<x-slot:preheader>{{ $variations === 1 ? 'Aprove o vídeo e ele entra no cardápio na hora.' : 'Escolha a melhor versão e ela entra no cardápio na hora.' }}</x-slot:preheader>
<x-slot:hero>
<x-mail::hero eyebrow="{{ $variations === 1 ? 'Vídeo pronto' : 'Vídeos prontos' }}" tone="success" :title="$dish.' ganhou vida.'">
@if ($variations === 1)
O vídeo ficou pronto. Aprove e ele entra no cardápio na hora.
@else
{{ $variations }} versões prontas. Escolha a melhor e ela entra no cardápio na hora.
@endif
</x-mail::hero>
</x-slot:hero>

Olá, **{{ $name }}**!

Assista às versões no painel e aprove a que mais combina com o prato.

<x-mail::button :url="$url">
{{ $variations === 1 ? 'Ver e aprovar o vídeo' : 'Escolher o vídeo' }}
</x-mail::button>

Nenhum vídeo aparece para os clientes antes da sua aprovação. Até lá, o cardápio continua como está.

<x-slot:subcopy>
Você recebe este aviso porque ativou os e-mails de vídeo pronto em Minha conta. Pode desligar por lá quando quiser.
</x-slot:subcopy>
</x-mail::message>
