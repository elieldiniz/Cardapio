<?php

use App\Actions\Customers\EraseCustomerData;
use App\Models\Customer;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Clientes (RF-19): quem já pediu, quanto gastou e "Apagar dados" (LGPD). A página é fina:
 * o apagamento fica em EraseCustomerData.
 */
new #[Layout('layouts::panel')] #[Title('Clientes')] class extends Component
{
    #[Url(as: 'busca', except: '')]
    public string $search = '';

    public bool $showErase = false;

    public ?int $erasingId = null;

    #[Computed]
    public function customers()
    {
        $term = trim($this->search);
        $digits = preg_replace('/\D/', '', $term);

        return auth()->user()->restaurant->customers()
            ->whereNull('erased_at')
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$term}%")
                ->when($digits !== '', fn ($phone) => $phone->orWhere('whatsapp', 'like', "%{$digits}%"))))
            ->withSum(['orders as spent' => fn ($query) => $query->whereHas('status', fn ($status) => $status->where('slug', 'finalizado'))], 'total')
            ->orderByDesc('last_order_at')
            ->limit(200)
            ->get();
    }

    public function confirmErase(int $id): void
    {
        $this->erasingId = auth()->user()->restaurant->customers()->findOrFail($id)->id;
        $this->showErase = true;
    }

    public function erase(EraseCustomerData $erase): void
    {
        $customer = auth()->user()->restaurant->customers()->findOrFail((int) $this->erasingId);

        try {
            $erase->handle($customer);
        } catch (ValidationException $exception) {
            $this->showErase = false;
            $this->erasingId = null;
            $this->dispatch('toast', message: $exception->errors()['customer'][0]);

            return;
        }

        $this->showErase = false;
        $this->erasingId = null;
        unset($this->customers);
        $this->dispatch('toast', message: 'Dados do cliente apagados.');
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Clientes" subtitle="Quem já pediu na sua loja. Ficam salvos nome e WhatsApp para agilizar os próximos pedidos." />

    <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar por nome ou WhatsApp" aria-label="Buscar cliente" data-customer-search />

    <x-ui.card :padding="false">
        @if ($this->customers->isEmpty())
            <x-ui.empty-state icon="users" title="Nenhum cliente ainda" description="Os clientes aparecem aqui depois do primeiro pedido." />
        @else
            <ul data-customers>
                @foreach ($this->customers as $customer)
                    <li wire:key="customer-{{ $customer->id }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-ink/[0.06] px-5 py-3.5 last:border-b-0" data-customer="{{ $customer->name }}">
                        <div class="min-w-0">
                            <p class="text-[14px] font-bold text-ink">{{ $customer->name }}
                                @if ($customer->isLoyal()) <span class="ml-1 rounded-full bg-accent/15 px-2 py-0.5 text-[11px] font-bold">Fiel</span> @endif
                            </p>
                            <p class="text-[12px] text-ink/50">
                                {{ $customer->orders_count }} {{ $customer->orders_count === 1 ? 'pedido' : 'pedidos' }}
                                · {{ Money::brl($customer->spent ?? 0) }} gastos
                                @if ($customer->last_order_at) · último em {{ $customer->last_order_at->format('d/m/Y') }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="https://api.whatsapp.com/send?phone={{ $customer->whatsapp }}" target="_blank" rel="noopener" class="text-[12.5px] font-bold text-emerald-700 hover:underline">WhatsApp</a>
                            <x-ui.button size="sm" variant="ghost" wire:click="confirmErase({{ $customer->id }})" data-erase>Apagar dados</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.modal wire:model="showErase" title="Apagar dados do cliente?" data-erase-modal>
        <p class="text-[13px] text-ink/60">O nome e o WhatsApp deste cliente somem da loja, inclusive dos pedidos antigos. Os valores e itens continuam nos relatórios. Isso não pode ser desfeito.</p>
        <x-slot:footer>
            <x-ui.button class="flex-1" wire:click="erase" wire:loading.attr="disabled" wire:target="erase" data-confirm-erase>Apagar dados</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
