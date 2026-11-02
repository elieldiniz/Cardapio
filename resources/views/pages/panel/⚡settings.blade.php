<?php

use App\Actions\Settings\SaveBusinessHours;
use App\Actions\Settings\SaveDeliveryZones;
use App\Actions\Settings\SaveOrderSettings;
use App\Support\BusinessHours;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Configurações do restaurante (RF-01, RF-02, RF-02e, UI-01): contato, Pix e
 * funcionamento. A página é fina: a validação e a gravação ficam nas Actions.
 */
new #[Layout('layouts::panel')] #[Title('Configurações')] class extends Component
{
    public const TIMEZONES = [
        'America/Porto_Velho' => 'Rondônia (Porto Velho)',
        'America/Manaus' => 'Amazonas (Manaus)',
        'America/Boa_Vista' => 'Roraima (Boa Vista)',
        'America/Rio_Branco' => 'Acre (Rio Branco)',
        'America/Cuiaba' => 'Mato Grosso (Cuiabá)',
        'America/Campo_Grande' => 'Mato Grosso do Sul (Campo Grande)',
        'America/Sao_Paulo' => 'Brasília (São Paulo, Rio, Minas, Sul)',
        'America/Bahia' => 'Bahia (Salvador)',
        'America/Fortaleza' => 'Nordeste (Fortaleza)',
        'America/Recife' => 'Pernambuco (Recife)',
        'America/Belem' => 'Pará (Belém)',
        'America/Noronha' => 'Fernando de Noronha',
    ];

    // Contato e Pix
    public string $whatsapp_number = '';

    public string $address = '';

    public string $pix_key = '';

    public string $pix_holder = '';

    public string $pix_city = '';

    // Funcionamento
    public bool $accepts_orders = true;

    public bool $hours_enabled = false;

    public bool $accepts_when_closed = false;

    public string $timezone = 'America/Porto_Velho';

    // Pizza meio a meio (RF-14a)
    public string $pizza_pricing_rule = 'maior';

    // Entrega
    public string $delivery_mode = 'a_combinar';

    public string $free_delivery_over = '';

    public string $min_order_delivery = '';

    public bool $accepts_other_neighborhood = true;

    // Taxa por distância (RF-24a): local da loja e faixas de km.
    public string $store_lat = '';

    public string $store_lng = '';

    /** @var array<int, array{from_km: string, to_km: string, fee: string}> */
    public array $tiers = [];

    /** @var array<int, array{id: ?int, name: string, fee: string, is_active: bool}> */
    public array $zones = [];

    /**
     * Turnos de cada dia da semana (0 = domingo … 6 = sábado).
     *
     * @var array<int, array<int, array{opens: string, closes: string}>>
     */
    public array $hours = [];

    public function mount(): void
    {
        $restaurant = auth()->user()->restaurant;
        $settings = $restaurant->orderSettings();

        $this->fill([
            'whatsapp_number' => (string) $settings->whatsapp_number,
            'address' => (string) $settings->address,
            'pix_key' => (string) $settings->pix_key,
            'pix_holder' => (string) $settings->pix_holder,
            'pix_city' => (string) $settings->pix_city,
            'accepts_orders' => (bool) $settings->accepts_orders,
            'hours_enabled' => (bool) $settings->hours_enabled,
            'accepts_when_closed' => (bool) $settings->accepts_when_closed,
            'timezone' => $settings->timezone,
            'pizza_pricing_rule' => $settings->pizza_pricing_rule ?? 'maior',
            'delivery_mode' => $settings->delivery_mode,
            'free_delivery_over' => $settings->free_delivery_over === null ? '' : Money::input($settings->free_delivery_over),
            'min_order_delivery' => $settings->min_order_delivery === null ? '' : Money::input($settings->min_order_delivery),
            'accepts_other_neighborhood' => (bool) $settings->accepts_other_neighborhood,
        ]);

        $this->store_lat = $settings->lat === null ? '' : rtrim(rtrim((string) $settings->lat, '0'), '.');
        $this->store_lng = $settings->lng === null ? '' : rtrim(rtrim((string) $settings->lng, '0'), '.');
        $this->tiers = $this->tiersOf($restaurant);

        $this->zones = $restaurant->deliveryZones->map(fn ($zone) => [
            'id' => $zone->id,
            'name' => $zone->name,
            'fee' => Money::input($zone->fee),
            'is_active' => $zone->is_active,
        ])->all();

        $this->hours = array_fill(0, 7, []);

        foreach ($restaurant->businessHours()->orderBy('weekday')->orderBy('shift')->get() as $hour) {
            $this->hours[$hour->weekday][] = [
                'opens' => substr($hour->opens_at, 0, 5),
                'closes' => substr($hour->closes_at, 0, 5),
            ];
        }
    }

    /**
     * Segunda primeiro, como o dono pensa a semana.
     *
     * @return array<int, array{weekday: int, label: string}>
     */
    #[Computed]
    public function weekdays(): array
    {
        $labels = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 0 => 'Domingo'];

        return array_map(fn (int $weekday) => ['weekday' => $weekday, 'label' => $labels[$weekday]], array_keys($labels));
    }

    #[Computed]
    public function state(): array
    {
        return BusinessHours::state(auth()->user()->restaurant->fresh());
    }

    #[Computed]
    public function testNumberUrl(): ?string
    {
        $number = SaveOrderSettings::normalizeWhatsApp($this->whatsapp_number);

        return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode('Teste de número do pedido — Degusta') : null;
    }

    public function saveContact(SaveOrderSettings $saveOrderSettings): void
    {
        $saveOrderSettings->handle(auth()->user()->restaurant, [
            'whatsapp_number' => $this->whatsapp_number,
            'address' => $this->address,
            'pix_key' => $this->pix_key,
            'pix_holder' => $this->pix_holder,
            'pix_city' => $this->pix_city,
        ]);

        $this->whatsapp_number = (string) auth()->user()->restaurant->fresh()->orderSettings()->whatsapp_number;
        unset($this->testNumberUrl);

        $this->dispatch('toast', message: 'Contato e Pix salvos.');
    }

    public function saveOperation(SaveOrderSettings $saveOrderSettings, SaveBusinessHours $saveBusinessHours): void
    {
        $restaurant = auth()->user()->restaurant;

        try {
            $saveBusinessHours->handle($restaurant, $this->hours);
        } catch (ValidationException $exception) {
            $this->addError('hours', collect($exception->errors())->flatten()->first() ?? 'Horário inválido.');

            return;
        }

        $saveOrderSettings->handle($restaurant, [
            'accepts_orders' => $this->accepts_orders,
            'hours_enabled' => $this->hours_enabled,
            'accepts_when_closed' => $this->accepts_when_closed,
            'timezone' => $this->timezone,
        ]);

        unset($this->state);

        $this->dispatch('toast', message: 'Funcionamento salvo.');
    }

    public function savePizzaRule(SaveOrderSettings $saveOrderSettings): void
    {
        $saveOrderSettings->handle(auth()->user()->restaurant, ['pizza_pricing_rule' => $this->pizza_pricing_rule]);

        $this->dispatch('toast', message: 'Regra da pizza salva.');
    }

    /** @return array<int, array{from_km: string, to_km: string, fee: string}> */
    private function tiersOf($restaurant): array
    {
        return $restaurant->distanceTiers()->get()->map(fn ($tier) => [
            'from_km' => rtrim(rtrim((string) $tier->from_km, '0'), '.') ?: '0',
            'to_km' => rtrim(rtrim((string) $tier->to_km, '0'), '.'),
            'fee' => Money::input($tier->fee),
        ])->all();
    }

    public function addTier(): void
    {
        $last = end($this->tiers);
        $this->tiers[] = ['from_km' => $last ? $last['to_km'] : '0', 'to_km' => '', 'fee' => ''];
    }

    public function removeTier(int $index): void
    {
        unset($this->tiers[$index]);
        $this->tiers = array_values($this->tiers);
    }

    public function addZone(): void
    {
        $this->zones[] = ['id' => null, 'name' => '', 'fee' => '', 'is_active' => true];
    }

    public function removeZone(int $index): void
    {
        unset($this->zones[$index]);
        $this->zones = array_values($this->zones);
    }

    public function saveDelivery(SaveDeliveryZones $saveDeliveryZones): void
    {
        $saveDeliveryZones->handle(auth()->user()->restaurant, $this->zones, [
            'delivery_mode' => $this->delivery_mode,
            'free_delivery_over' => $this->free_delivery_over,
            'min_order_delivery' => $this->min_order_delivery,
            'accepts_other_neighborhood' => $this->accepts_other_neighborhood,
            'lat' => $this->store_lat,
            'lng' => $this->store_lng,
        ]);

        if ($this->delivery_mode === 'distancia') {
            app(\App\Actions\Settings\SaveDistanceTiers::class)->handle(auth()->user()->restaurant, $this->tiers);
            $this->tiers = $this->tiersOf(auth()->user()->restaurant);
        }

        $this->zones = auth()->user()->restaurant->deliveryZones()->get()->map(fn ($zone) => [
            'id' => $zone->id,
            'name' => $zone->name,
            'fee' => Money::input($zone->fee),
            'is_active' => $zone->is_active,
        ])->all();

        $this->dispatch('toast', message: 'Entrega salva.');
    }

    public function addShift(int $weekday): void
    {
        if (count($this->hours[$weekday] ?? []) >= SaveBusinessHours::MAX_SHIFTS_PER_DAY) {
            return;
        }

        $this->hours[$weekday][] = ['opens' => '18:00', 'closes' => '23:00'];
    }

    public function removeShift(int $weekday, int $index): void
    {
        unset($this->hours[$weekday][$index]);
        $this->hours[$weekday] = array_values($this->hours[$weekday]);
    }

    public function repeatMondayToFriday(): void
    {
        foreach ([2, 3, 4, 5] as $weekday) {
            $this->hours[$weekday] = $this->hours[1] ?? [];
        }
    }
};
?>

<div class="flex flex-col gap-[18px]">
    <x-ui.page-header title="Configurações" subtitle="Contato para os pedidos, Pix e horário de funcionamento." />

    @php($state = $this->state)
    <div
        data-store-state
        class="flex items-center gap-2 rounded-[12px] px-4 py-3 text-[13.5px] font-semibold {{ $state['open'] ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' }}"
    >
        <span class="size-2 rounded-full {{ $state['open'] ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
        Como os clientes veem agora: {{ $state['label'] }}
    </div>

    <x-ui.card title="Contato e Pix">
        <form wire:submit="saveContact" class="grid gap-4 sm:grid-cols-2">
            <x-ui.field label="WhatsApp que recebe os pedidos" for="whatsapp_number" error="whatsapp_number" hint="DDD + número, ex.: (69) 99604-1447.">
                <x-ui.input id="whatsapp_number" wire:model="whatsapp_number" inputmode="tel" autocomplete="off" placeholder="(11) 98765-4321" />
            </x-ui.field>
            <div class="flex items-end pb-1">
                @if ($this->testNumberUrl)
                    <a href="{{ $this->testNumberUrl }}" target="_blank" rel="noopener" data-test-number class="text-[13px] font-bold text-accent hover:underline">Testar este número</a>
                @else
                    <span class="text-[12.5px] text-ink/40">Salve um número válido para testar.</span>
                @endif
            </div>

            <x-ui.field label="Endereço da loja" for="address" error="address" class="sm:col-span-2">
                <x-ui.input id="address" wire:model="address" autocomplete="street-address" placeholder="Rua, número — bairro, cidade" />
            </x-ui.field>

            <x-ui.field label="Chave Pix" for="pix_key" error="pix_key" hint="Recomendado: chave aleatória ou e-mail.">
                <x-ui.input id="pix_key" wire:model="pix_key" autocomplete="off" />
            </x-ui.field>
            <x-ui.field label="Nome do titular" for="pix_holder" error="pix_holder" hint="Como aparece no banco.">
                <x-ui.input id="pix_holder" wire:model="pix_holder" autocomplete="off" />
            </x-ui.field>
            <x-ui.field label="Cidade do Pix" for="pix_city" error="pix_city">
                <x-ui.input id="pix_city" wire:model="pix_city" autocomplete="off" placeholder="Porto Velho" />
            </x-ui.field>

            <div class="sm:col-span-2">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveContact">Salvar contato e Pix</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Pizza meio a meio">
        <form wire:submit="savePizzaRule" class="flex flex-col gap-3" data-pizza-rule>
            <p class="text-[13px] text-ink/60">Vale para todas as pizzas da loja quando o cliente escolhe mais de um sabor.</p>
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-[13px] has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                    <input type="radio" wire:model="pizza_pricing_rule" value="maior" class="sr-only">
                    <span class="font-bold text-ink">Pelo sabor mais caro <span class="ml-1 rounded-full bg-accent px-2 py-0.5 text-[10px] uppercase text-white">Recomendado</span></span>
                    <span class="text-ink/55">Padrão das pizzarias. Ex.: ½ R$ 55 + ½ R$ 62 = R$ 62</span>
                </label>
                <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-[13px] has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                    <input type="radio" wire:model="pizza_pricing_rule" value="media" class="sr-only">
                    <span class="font-bold text-ink">Pela média dos sabores</span>
                    <span class="text-ink/55">Ex.: ½ R$ 55 + ½ R$ 62 = R$ 58,50</span>
                </label>
            </div>
            @error('pizza_pricing_rule') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
            <div><x-ui.button type="submit" wire:loading.attr="disabled" wire:target="savePizzaRule">Salvar regra</x-ui.button></div>
        </form>
    </x-ui.card>

    <x-ui.card title="Entrega">
        <form wire:submit="saveDelivery" class="flex flex-col gap-4" data-delivery-form>
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-[13px] has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                    <input type="radio" wire:model.live="delivery_mode" value="a_combinar" class="sr-only">
                    <span class="font-bold text-ink">A combinar</span>
                    <span class="text-ink/55">Você informa a taxa pelo WhatsApp ao aceitar o pedido.</span>
                </label>
                <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-[13px] has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                    <input type="radio" wire:model.live="delivery_mode" value="bairro" class="sr-only">
                    <span class="font-bold text-ink">Por bairro</span>
                    <span class="text-ink/55">O cliente escolhe o bairro numa lista e já vê a taxa.</span>
                </label>
                <label class="flex cursor-pointer flex-col rounded-xl border p-3 text-[13px] has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                    <input type="radio" wire:model.live="delivery_mode" value="distancia" class="sr-only" data-mode-distance>
                    <span class="font-bold text-ink">Por distância</span>
                    <span class="text-ink/55">A taxa depende dos km da loja até o cliente.</span>
                </label>
            </div>
            @error('delivery_mode') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror

            @if ($delivery_mode === 'distancia')
                <div class="flex flex-col gap-2.5" data-tiers>
                    <h3 class="text-[13px] font-bold text-ink">Local da loja</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.field label="Latitude" for="store_lat" error="lat" hint="Ex.: -8.76194 (clique com o botão direito na loja no Google Maps e copie)."><x-ui.input id="store_lat" wire:model="store_lat" inputmode="decimal" /></x-ui.field>
                        <x-ui.field label="Longitude" for="store_lng" error="lng"><x-ui.input id="store_lng" wire:model="store_lng" inputmode="decimal" /></x-ui.field>
                    </div>
                    <h3 class="mt-1 text-[13px] font-bold text-ink">Faixas de distância</h3>
                    @error('tiers') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror
                    @foreach ($tiers as $index => $tier)
                        <div wire:key="tier-{{ $index }}" class="flex flex-wrap items-start gap-2">
                            <x-ui.field error="tiers.{{ $index }}.to_km" class="w-24"><x-ui.input wire:model="tiers.{{ $index }}.from_km" placeholder="De km" inputmode="decimal" aria-label="Faixa {{ $index + 1 }} começa em km" /></x-ui.field>
                            <x-ui.field class="w-24"><x-ui.input wire:model="tiers.{{ $index }}.to_km" placeholder="Até km" inputmode="decimal" aria-label="Faixa {{ $index + 1 }} termina em km" /></x-ui.field>
                            <x-ui.field error="tiers.{{ $index }}.fee" class="w-28"><x-ui.input wire:model="tiers.{{ $index }}.fee" placeholder="R$ 0,00" inputmode="decimal" aria-label="Taxa da faixa {{ $index + 1 }}" /></x-ui.field>
                            <x-ui.button size="sm" variant="danger" type="button" wire:click="removeTier({{ $index }})" class="mt-1">Remover</x-ui.button>
                        </div>
                    @endforeach
                    <div><x-ui.button size="sm" variant="secondary" type="button" wire:click="addTier">Adicionar faixa</x-ui.button></div>
                    <p class="text-[12px] text-ink/50">Fora das faixas, ou se o cliente não compartilhar a localização, a taxa fica “a combinar”.</p>
                </div>
            @endif

            @if ($delivery_mode === 'bairro')
                <div class="flex flex-col gap-2.5" data-zones>
                    <h3 class="text-[13px] font-bold text-ink">Bairros atendidos</h3>
                    @error('zones') <p class="text-[12.5px] text-danger" role="alert">{{ $message }}</p> @enderror

                    @foreach ($zones as $index => $zone)
                        <div wire:key="zone-{{ $index }}" class="flex flex-wrap items-start gap-2">
                            <x-ui.field error="zones.{{ $index }}.name" class="min-w-[160px] flex-1">
                                <x-ui.input wire:model="zones.{{ $index }}.name" placeholder="Nome do bairro" aria-label="Nome do bairro {{ $index + 1 }}" />
                            </x-ui.field>
                            <x-ui.field error="zones.{{ $index }}.fee" class="w-32">
                                <x-ui.input wire:model="zones.{{ $index }}.fee" placeholder="R$ 0,00" inputmode="decimal" aria-label="Taxa do bairro {{ $index + 1 }}" />
                            </x-ui.field>
                            <label class="flex items-center gap-1.5 pt-2.5 text-[12px] font-semibold text-ink/60">
                                <input type="checkbox" wire:model="zones.{{ $index }}.is_active" class="size-4 accent-[var(--accent)]"> Ativo
                            </label>
                            <x-ui.button size="sm" variant="danger" type="button" wire:click="removeZone({{ $index }})" class="mt-1">Remover</x-ui.button>
                        </div>
                    @endforeach

                    <div><x-ui.button size="sm" variant="secondary" type="button" wire:click="addZone">Adicionar bairro</x-ui.button></div>

                    <label class="flex items-start gap-3 text-[13.5px] font-semibold text-ink">
                        <input type="checkbox" wire:model="accepts_other_neighborhood" class="mt-0.5 size-4 accent-[var(--accent)]">
                        <span>Aceitar “Outro bairro” <span class="block text-[12px] font-normal text-ink/55">Cliente de bairro fora da lista pede com taxa a combinar. Você informa ao aceitar.</span></span>
                    </label>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Entrega grátis acima de (opcional)" for="free_delivery_over" error="free_delivery_over" hint="O cardápio mostra “faltam R$ X para entrega grátis”.">
                    <x-ui.input id="free_delivery_over" wire:model="free_delivery_over" placeholder="R$ 80,00" inputmode="decimal" />
                </x-ui.field>
                <x-ui.field label="Pedido mínimo para entrega (opcional)" for="min_order_delivery" error="min_order_delivery" hint="Abaixo disso o cliente só pode retirar na loja.">
                    <x-ui.input id="min_order_delivery" wire:model="min_order_delivery" placeholder="R$ 25,00" inputmode="decimal" />
                </x-ui.field>
            </div>

            <div>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveDelivery">Salvar entrega</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Funcionamento">
        <form wire:submit="saveOperation" class="flex flex-col gap-4">
            <label class="flex items-start gap-3 text-[13.5px] font-semibold text-ink">
                <input type="checkbox" wire:model="accepts_orders" class="mt-0.5 size-4 accent-[var(--accent)]">
                <span>
                    Loja aberta para pedidos
                    <span class="block text-[12px] font-normal text-ink/55">Desligue para fechar na hora. Esta chave vale mais que o horário automático.</span>
                </span>
            </label>

            <label class="flex items-start gap-3 text-[13.5px] font-semibold text-ink">
                <input type="checkbox" wire:model.live="hours_enabled" class="mt-0.5 size-4 accent-[var(--accent)]">
                <span>
                    Abrir e fechar sozinho
                    <span class="block text-[12px] font-normal text-ink/55">O cardápio mostra "Aberto agora" ou "Fechado · abre às 18:00" conforme o horário abaixo.</span>
                </span>
            </label>

            <x-ui.field label="Fuso horário da loja" for="timezone" error="timezone" hint="Define o horário usado no aberto/fechado, no caixa e nos relatórios.">
                <x-ui.select id="timezone" wire:model="timezone">
                    @foreach (self::TIMEZONES as $zone => $label)
                        <option value="{{ $zone }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>

            @if ($hours_enabled)
                <div class="flex flex-col gap-3" data-hours-grid>
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-[13px] font-bold text-ink">Horário por dia</h3>
                        <button type="button" wire:click="repeatMondayToFriday" class="text-[12.5px] font-bold text-accent hover:underline">Repetir de segunda a sexta</button>
                    </div>

                    @error('hours')
                        <p class="text-[12.5px] font-medium text-danger" role="alert">{{ $message }}</p>
                    @enderror

                    @foreach ($this->weekdays as $day)
                        <div class="flex flex-wrap items-start gap-3 border-t border-ink/10 pt-3" wire:key="day-{{ $day['weekday'] }}">
                            <span class="w-20 pt-2.5 text-[13px] font-bold text-ink">{{ $day['label'] }}</span>

                            <div class="flex flex-1 flex-col gap-2">
                                @forelse ($hours[$day['weekday']] ?? [] as $index => $shift)
                                    <div class="flex flex-wrap items-center gap-2" wire:key="shift-{{ $day['weekday'] }}-{{ $index }}">
                                        <x-ui.input type="time" class="!w-auto" wire:model="hours.{{ $day['weekday'] }}.{{ $index }}.opens" aria-label="{{ $day['label'] }} abre" />
                                        <span class="text-[12.5px] text-ink/50">às</span>
                                        <x-ui.input type="time" class="!w-auto" wire:model="hours.{{ $day['weekday'] }}.{{ $index }}.closes" aria-label="{{ $day['label'] }} fecha" />
                                        <button type="button" wire:click="removeShift({{ $day['weekday'] }}, {{ $index }})" class="text-[12px] font-bold text-danger hover:underline">Remover</button>
                                    </div>
                                @empty
                                    <span class="pt-2.5 text-[13px] text-ink/45">Fechado</span>
                                @endforelse

                                @if (count($hours[$day['weekday']] ?? []) < \App\Actions\Settings\SaveBusinessHours::MAX_SHIFTS_PER_DAY)
                                    <button type="button" wire:click="addShift({{ $day['weekday'] }})" class="self-start text-[12.5px] font-bold text-accent hover:underline">+ turno</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <label class="flex items-start gap-3 text-[13.5px] font-semibold text-ink">
                    <input type="checkbox" wire:model="accepts_when_closed" class="mt-0.5 size-4 accent-[var(--accent)]">
                    <span>
                        Aceitar pedidos com a loja fechada
                        <span class="block text-[12px] font-normal text-ink/55">O cliente pode enviar e é avisado que você confirma quando abrir.</span>
                    </span>
                </label>
            @endif

            <div>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveOperation">Salvar funcionamento</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
