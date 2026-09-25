<x-mail::message>
<x-slot:preheader>Seu cardápio segue no ar. Nada foi apagado.</x-slot:preheader>
<x-slot:hero>
<x-mail::hero :eyebrow="$forNonPayment ? 'Pagamento não aprovado' : 'Mudança de plano'" :tone="$forNonPayment ? 'danger' : 'accent'" title="Seu restaurante voltou ao plano Grátis.">
O cardápio continua no ar e o QR Code das mesas segue funcionando.
</x-mail::hero>
</x-slot:hero>

Olá, **{{ $name }}**!

@if ($forNonPayment)
Tentamos cobrar a assinatura algumas vezes e nenhuma tentativa foi aprovada. Por isso{{ $restaurant ? ' o **'.$restaurant.'**' : ' seu restaurante' }} passou para o plano Grátis.
@else
A assinatura foi encerrada e{{ $restaurant ? ' o **'.$restaurant.'**' : ' seu restaurante' }} passou para o plano Grátis.
@endif

@if ($hiddenDishes !== [])
<x-mail::callout tone="accent" :title="'O plano Grátis mostra até '.$dishLimit.' pratos. Estes saíram do cardápio:'">
@foreach ($hiddenDishes as $dish)
- {{ $dish }}
@endforeach
</x-mail::callout>
@endif

**Nada foi apagado.** Vídeos, fotos e preços continuam guardados. Assine de novo e tudo volta a aparecer para os clientes na hora.

<x-mail::button :url="$url">
Reativar minha assinatura
</x-mail::button>

<x-slot:subcopy>
Se o botão não funcionar, copie e cole este endereço no navegador: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
