<x-mail::layout>
{{-- Brand, on the dark hero --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" />
</x-slot:header>

@isset($hero)
<x-slot:hero>
{!! $hero !!}
</x-slot:hero>
@endisset

@isset($preheader)
<x-slot:preheader>{{ $preheader }}</x-slot:preheader>
@endisset

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
<img src="cid:wordmark-dark" class="wordmark-footer" width="82" height="29" alt="{{ config('app.name') }}" style="color: #1a1815; font-family: Georgia, 'Times New Roman', serif; font-size: 20px; font-style: italic;"><br>
Cardápio em vídeo pelo QR Code: o cliente vê cada prato antes de pedir.

[Abrir o painel]({{ route('panel.home') }}) · [Falar com a gente]({{ route('marketing.contact') }})

Você recebeu este e-mail porque tem uma conta no {{ config('app.name') }}.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
