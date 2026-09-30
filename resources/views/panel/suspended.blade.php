@php $contact = config('landing.contact'); @endphp
<x-layouts.minimal title="Conta suspensa">
    <h1 class="font-serif text-[26px] text-ink">Conta suspensa</h1>
    <p class="mt-2 text-[14px] leading-relaxed text-ink/60">O acesso ao painel de <strong class="text-ink">{{ $restaurant->name }}</strong> está suspenso no momento. Fale com o suporte para regularizar.</p>
    <div class="mt-5 flex flex-wrap gap-2">
        @if ($contact['whatsapp'])
            <x-ui.button :href="'https://wa.me/'.$contact['whatsapp']" target="_blank" rel="noopener">Falar no WhatsApp</x-ui.button>
        @endif
        @if ($contact['email'])
            <x-ui.button variant="secondary" :href="'mailto:'.$contact['email']">{{ $contact['email'] }}</x-ui.button>
        @endif
    </div>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <x-ui.button type="submit" variant="secondary">Sair</x-ui.button>
    </form>
</x-layouts.minimal>
