<x-layouts.minimal title="Conta suspensa">
    <h1 class="font-serif text-[26px] text-ink">Conta suspensa</h1>
    <p class="mt-2 text-[14px] leading-relaxed text-ink/60">O acesso ao painel de <strong class="text-ink">{{ $restaurant->name }}</strong> está suspenso no momento. Fale com o suporte para regularizar.</p>
    <form method="POST" action="{{ route('logout') }}" class="mt-5">
        @csrf
        <x-ui.button type="submit" variant="secondary">Sair</x-ui.button>
    </form>
</x-layouts.minimal>
