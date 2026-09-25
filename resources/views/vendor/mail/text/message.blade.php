<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    @isset($hero)
        {{ $hero }}
    @endisset

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            {{ config('app.name') }} · Cardápio em vídeo pelo QR Code · {{ config('app.url') }}
            Você recebeu este e-mail porque tem uma conta no {{ config('app.name') }}.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
