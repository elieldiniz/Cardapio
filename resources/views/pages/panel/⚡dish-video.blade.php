<?php

use App\Actions\Ai\RequestVideoGeneration;
use App\Actions\Videos\ApproveVideo;
use App\Actions\Videos\StartOwnVideoUpload;
use App\Exceptions\OwnVideoRejectedException;
use App\Exceptions\InsufficientGenerationBalanceException;
use App\Exceptions\VideoGenerationUnavailableException;
use App\Models\Dish;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::panel')] class extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $dishId;

    /** @var array<int, int> dish_photos ids, in the order the dono picked them (angle order). */
    public array $selectedPhotoIds = [];

    public int $variations = RequestVideoGeneration::MIN_VARIATIONS;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newPhotos = [];

    public bool $showGenerateForm = false;

    public function mount(Dish $dish): void
    {
        abort_unless($dish->restaurant_id === auth()->user()->restaurant_id, 404);

        $this->dishId = $dish->id;
        $this->selectedPhotoIds = $dish->photos()->orderBy('display_order')->limit(RequestVideoGeneration::MAX_PHOTOS)->pluck('id')->all();
        $this->showGenerateForm = $dish->videoGenerations()->doesntExist() && $dish->videos()->doesntExist();
    }

    public function render()
    {
        return $this->view()->title('Vídeo · '.$this->dish->name);
    }

    #[Computed]
    public function dish(): Dish
    {
        return auth()->user()->restaurant->dishes()->with(['activeVideo', 'photos' => fn ($query) => $query->orderBy('display_order')])->findOrFail($this->dishId);
    }

    #[Computed]
    public function restaurant()
    {
        return auth()->user()->restaurant->load('generationBalance');
    }

    #[Computed]
    public function preset()
    {
        return RequestVideoGeneration::activePreset();
    }

    #[Computed]
    public function available(): int
    {
        return $this->restaurant->availableGenerations();
    }

    /**
     * Newest first, each with its variations.
     */
    #[Computed]
    public function generations()
    {
        return $this->dish->videoGenerations()
            ->with(['status', 'videos.status', 'photos'])
            ->latest('id')
            ->get();
    }

    /**
     * Videos not tied to an AI generation (own uploads, Phase 13), newest first.
     */
    #[Computed]
    public function uploads()
    {
        return $this->dish->videos()->whereNull('generation_id')->with(['status', 'origin'])->latest('id')->get();
    }

    #[Computed]
    public function isWorking(): bool
    {
        return $this->generations->contains(fn ($generation) => ! $generation->isFinished())
            || $this->dish->videos()->whereHas('status', fn ($status) => $status->where('slug', 'processando'))->exists();
    }

    public function uploadPhotos(): void
    {
        $this->validate(['newPhotos.*' => ['image', 'max:8192']], [
            'newPhotos.*.image' => 'Envie apenas imagens.',
            'newPhotos.*.max' => 'Cada foto pode ter no máximo 8 MB.',
        ]);

        $order = (int) $this->dish->photos()->max('display_order');

        foreach ($this->newPhotos as $upload) {
            $photo = $this->dish->photos()->create([
                'file_path' => $upload->store("dish-photos/{$this->dish->restaurant_id}", 'public'),
                'display_order' => ++$order,
            ]);

            if (count($this->selectedPhotoIds) < RequestVideoGeneration::MAX_PHOTOS) {
                $this->selectedPhotoIds[] = $photo->id;
            }
        }

        $this->reset('newPhotos');
        unset($this->dish);
    }

    public function togglePhoto(int $photoId): void
    {
        if (in_array($photoId, $this->selectedPhotoIds, true)) {
            $this->selectedPhotoIds = array_values(array_diff($this->selectedPhotoIds, [$photoId]));
        } elseif (count($this->selectedPhotoIds) < RequestVideoGeneration::MAX_PHOTOS) {
            $this->selectedPhotoIds[] = $photoId;
        }
    }

    public function generate(RequestVideoGeneration $request): void
    {
        $this->validate([
            'selectedPhotoIds' => ['required', 'array', 'min:1', 'max:4'],
            'variations' => ['required', 'integer', 'between:2,3'],
        ], [
            'selectedPhotoIds.required' => 'Escolha pelo menos 1 foto.',
            'selectedPhotoIds.min' => 'Escolha pelo menos 1 foto.',
            'selectedPhotoIds.max' => 'Escolha no máximo 4 fotos.',
        ]);

        try {
            $request->handle($this->dish, $this->selectedPhotoIds, $this->variations);
        } catch (InsufficientGenerationBalanceException) {
            $this->addError('variations', 'Saldo insuficiente para esta geração.');

            return;
        } catch (VideoGenerationUnavailableException|InvalidArgumentException $exception) {
            $this->addError('selectedPhotoIds', $exception->getMessage());

            return;
        }

        $this->showGenerateForm = false;
        unset($this->generations, $this->available, $this->restaurant);

        $this->dispatch('toast', message: 'Geração na fila! Avisaremos quando os vídeos estiverem prontos.');
    }

    /**
     * Called by the browser after reading the file's metadata; returns the Mux
     * direct-upload URL the browser PUTs the file to (US-4.1).
     *
     * @return array{ok: bool, error?: string, upload_url?: string, warnings?: array<int, string>}
     */
    public function startUpload(float $duration, int $width, int $height, StartOwnVideoUpload $startUpload): array
    {
        try {
            $result = $startUpload->handle($this->dish, $duration, $width, $height);
        } catch (OwnVideoRejectedException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }

        unset($this->uploads);

        return ['ok' => true, 'upload_url' => $result['upload_url'], 'warnings' => $result['warnings']];
    }

    public function uploadFinished(): void
    {
        unset($this->uploads, $this->isWorking);

        $this->dispatch('toast', message: 'Vídeo enviado! Ele está sendo processado e avisaremos quando puder aprovar.');
    }

    public function approve(int $videoId, ApproveVideo $approveVideo): void
    {
        $video = Video::query()->where('dish_id', $this->dishId)->findOrFail($videoId);

        try {
            $approveVideo->handle($video);
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');

            return;
        }

        unset($this->dish, $this->generations, $this->uploads);

        $this->dispatch('toast', message: 'Vídeo aprovado! Ele já aparece no cardápio.');
    }
};
?>

@php
    $balance = $this->restaurant->generationBalance;
    $latestGeneration = $this->generations->first();
    $active = $this->dish->activeVideo;
    $canCover = $this->available >= $variations;
@endphp

<div class="flex flex-col gap-[18px]" @if ($this->isWorking) wire:poll.5s @endif>
    <x-ui.page-header :title="'Vídeo · '.$this->dish->name" subtitle="Nada vai ao ar sem a sua aprovação.">
        <x-ui.button variant="secondary" :href="route('panel.dishes.edit', $this->dish)" wire:navigate>Voltar ao prato</x-ui.button>
    </x-ui.page-header>

    <x-ui.card title="Vídeo no cardápio">
        @if ($active)
            <div class="flex flex-wrap items-start gap-4">
                @include('pages.panel.partials.video-player', ['video' => $active])
                <div class="flex flex-col gap-1.5 text-[13.5px] text-ink/65">
                    <span class="inline-flex w-fit rounded-full bg-success/12 px-3 py-1 text-[11px] font-bold tracking-wide text-success uppercase">Aprovado · no ar</span>
                    <span>Origem: {{ $active->generation_id ? 'gerado por IA' : 'vídeo próprio' }}</span>
                    @if ($active->duration_seconds)
                        <span>Duração: {{ $active->duration_seconds }}s</span>
                    @endif
                </div>
            </div>
        @else
            <x-ui.empty-state icon="media" title="Sem vídeo aprovado" description="Este prato ainda não aparece no feed. Gere um vídeo por IA ou envie um vídeo próprio e aprove." />
        @endif
    </x-ui.card>

    {{-- ===== Gerar com IA ===== --}}
    <x-ui.card title="Gerar com IA">
        <x-slot:actions>
            @unless ($showGenerateForm)
                <x-ui.button size="sm" wire:click="$set('showGenerateForm', true)">
                    <x-ui.icon name="sparkles" class="size-4" />
                    {{ $latestGeneration ? 'Gerar de novo' : 'Gerar vídeo' }}
                </x-ui.button>
            @endunless
        </x-slot:actions>

        @if (! $this->preset)
            <x-ui.error-state title="Geração indisponível" description="A geração de vídeo por IA está indisponível no momento. Você ainda pode enviar um vídeo próprio." />
        @elseif ($showGenerateForm)
            <form wire:submit="generate" class="flex flex-col gap-4" data-generate-form>
                <div class="rounded-xl bg-sand/70 p-3.5 text-[13px] leading-relaxed text-ink/70">
                    <strong class="text-ink">Recomendado: 3 a 4 fotos</strong> do prato em ângulos diferentes — o vídeo ganha um giro mais completo.
                    Com 1 foto também funciona, com um movimento de câmera mais curto.
                </div>

                <div>
                    <p class="mb-2 text-[12px] font-semibold text-ink/60">Fotos ({{ count($selectedPhotoIds) }}/4 selecionadas — a ordem de escolha define os ângulos)</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($this->dish->photos as $photo)
                            @php $position = array_search($photo->id, $selectedPhotoIds, true); @endphp
                            <button
                                type="button"
                                wire:key="pick-{{ $photo->id }}"
                                wire:click="togglePhoto({{ $photo->id }})"
                                class="relative size-24 overflow-hidden rounded-[10px] bg-ink/[0.06] transition {{ $position !== false ? 'ring-3 ring-accent' : 'opacity-70 hover:opacity-100' }}"
                                aria-pressed="{{ $position !== false ? 'true' : 'false' }}"
                            >
                                <img src="{{ Storage::disk('public')->url($photo->file_path) }}" alt="" class="size-full object-cover">
                                @if ($position !== false)
                                    <span class="absolute top-1 left-1 flex size-6 items-center justify-center rounded-full bg-accent text-[12px] font-bold text-white">{{ $position + 1 }}</span>
                                @endif
                            </button>
                        @endforeach

                        <label class="flex size-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-[10px] border-2 border-dashed border-ink/15 text-[11.5px] font-semibold text-ink/50 hover:border-accent hover:text-accent">
                            <x-ui.icon name="upload" />
                            Nova foto
                            <input type="file" wire:model="newPhotos" x-on:livewire-upload-finish="$wire.uploadPhotos()" accept="image/*" multiple class="sr-only">
                        </label>
                    </div>
                    <div wire:loading wire:target="newPhotos" class="mt-2 text-[12.5px] text-ink/55">Enviando fotos…</div>
                    @error('selectedPhotoIds') <p class="mt-2 text-[12px] text-danger">{{ $message }}</p> @enderror
                    @error('newPhotos.*') <p class="mt-2 text-[12px] text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <p class="mb-2 text-[12px] font-semibold text-ink/60">Quantas variações você quer comparar?</p>
                    <div class="flex gap-2">
                        @foreach ([2, 3] as $option)
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="variations" value="{{ $option }}" class="peer sr-only">
                                <span class="inline-flex rounded-full border border-ink/15 px-4 py-2 text-[13px] font-bold text-ink/70 peer-checked:border-accent peer-checked:bg-accent peer-checked:text-white">{{ $option }} variações</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-1 rounded-xl border border-ink/10 p-3.5 text-[13.5px]" data-balance-summary>
                    <span>Esta geração vai consumir <strong data-consumption>{{ $variations }} {{ $variations === 1 ? 'geração' : 'gerações' }}</strong> (1 por variação).</span>
                    <span class="text-ink/60">
                        Saldo disponível: <strong class="text-ink">{{ $this->available }}</strong>
                        (mensal {{ $balance?->monthly_balance ?? 0 }} + avulso {{ $balance?->addon_balance ?? 0 }}{{ $this->restaurant->reservedGenerations() ? ', '.$this->restaurant->reservedGenerations().' reservadas em gerações na fila' : '' }}).
                    </span>
                    <span class="text-[12.5px] text-ink/50">O saldo só é debitado quando os vídeos ficam prontos. Se o provedor falhar, nada é cobrado.</span>
                </div>

                @if (! $canCover)
                    <div class="flex flex-col gap-2.5 rounded-xl bg-danger/8 p-3.5 text-[13.5px] text-ink/75" role="alert" data-balance-blocked>
                        <span><strong class="text-danger">Saldo insuficiente.</strong> Você precisa de {{ $variations }} e tem {{ $this->available }} disponíveis.</span>
                        <div class="flex flex-wrap gap-2">
                            @if (Route::has('panel.subscription'))
                                <x-ui.button size="sm" :href="route('panel.subscription')" wire:navigate>Fazer upgrade de plano</x-ui.button>
                                <x-ui.button size="sm" variant="secondary" :href="route('panel.subscription').'#pacotes'" wire:navigate>Comprar pacote de gerações</x-ui.button>
                            @endif
                        </div>
                    </div>
                @endif
                @error('variations') <p class="text-[12px] text-danger">{{ $message }}</p> @enderror

                <div class="flex gap-2.5">
                    <x-ui.button type="submit" :disabled="! $canCover || empty($selectedPhotoIds)" wire:loading.attr="disabled" wire:target="generate">Confirmar e gerar</x-ui.button>
                    @if ($latestGeneration)
                        <x-ui.button variant="secondary" wire:click="$set('showGenerateForm', false)">Cancelar</x-ui.button>
                    @endif
                </div>
            </form>
        @elseif (! $latestGeneration)
            <p class="text-[13.5px] text-ink/55">Transforme as fotos do prato num vídeo curto, sempre no mesmo estilo visual.</p>
        @endif

        {{-- ===== Rodadas de geração ===== --}}
        @foreach ($this->generations as $generation)
            @php
                $isLatest = $loop->first;
                $statusLabels = ['fila' => 'Na fila', 'gerando' => 'Gerando…', 'pronto' => 'Pronto', 'erro' => 'Erro'];
            @endphp
            <div wire:key="generation-{{ $generation->id }}" class="mt-5 border-t border-ink/[0.06] pt-4 first:mt-4" data-generation="{{ $generation->id }}">
                <div class="mb-3 flex flex-wrap items-center gap-2 text-[13px]">
                    <span class="font-bold text-ink">{{ $isLatest ? 'Rodada mais recente' : 'Rodada anterior' }}</span>
                    <span class="text-ink/45">· {{ $generation->created_at->format('d/m H:i') }} · {{ $generation->variations_requested }} variações · {{ $generation->photos->count() }} {{ $generation->photos->count() === 1 ? 'foto' : 'fotos' }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase {{ $generation->status->slug === 'erro' ? 'bg-danger/12 text-danger' : 'bg-ink/8 text-ink/60' }}">{{ $statusLabels[$generation->status->slug] ?? $generation->status->name }}</span>
                </div>

                @if ($generation->status->slug === 'erro')
                    <p class="text-[13px] text-ink/60">O provedor de IA falhou nesta rodada. Nenhuma geração foi descontada do seu saldo.</p>
                @elseif (! $generation->isFinished())
                    <x-ui.skeleton rows="1" avatar />
                @endif

                <div class="flex flex-wrap gap-4">
                    @foreach ($generation->videos as $video)
                        @include('pages.panel.partials.video-review', ['video' => $video, 'discarded' => ! $isLatest])
                    @endforeach
                </div>
            </div>
        @endforeach
    </x-ui.card>

    {{-- ===== Vídeo próprio (US-4.1, US-4.2) ===== --}}
    <x-ui.card title="Enviar vídeo próprio">
        <div
            x-data="ownVideoUpload"
            class="flex flex-col gap-4"
            data-own-upload
        >
            <ul class="flex flex-col gap-1 rounded-xl bg-sand/70 p-3.5 text-[13px] text-ink/70">
                <li><strong class="text-ink">Vertical</strong> (celular em pé, 9:16)</li>
                <li><strong class="text-ink">De 5 a 15 segundos</strong> — vídeos fora disso são recusados</li>
                <li><strong class="text-ink">Prato em destaque</strong>, bem iluminado</li>
                <li class="text-ink/50">Enviar vídeo próprio não consome gerações.</li>
            </ul>

            <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-ink/15 px-4 py-6 text-[13.5px] font-semibold text-ink/60 hover:border-accent hover:text-accent" :class="busy && 'pointer-events-none opacity-60'">
                <x-ui.icon name="upload" />
                <span x-text="busy ? 'Enviando… ' + progress + '%' : 'Escolher vídeo (MP4 ou MOV)'"></span>
                <input type="file" accept="video/*" class="sr-only" x-on:change="pick($event)" :disabled="busy">
            </label>

            <div x-show="busy" x-cloak class="h-2 overflow-hidden rounded-full bg-ink/10">
                <div class="h-full bg-accent transition-all" :style="`width: ${progress}%`"></div>
            </div>

            <template x-if="error">
                <div class="rounded-xl bg-danger/8 p-3.5 text-[13.5px] font-medium text-danger" role="alert" x-text="error"></div>
            </template>
            <template x-for="warning in warnings" :key="warning">
                <div class="rounded-xl bg-accent/10 p-3.5 text-[13.5px] font-medium text-ink/75" role="status" x-text="warning"></div>
            </template>

            @if ($this->uploads->isNotEmpty())
                <div class="flex flex-wrap gap-4 border-t border-ink/[0.06] pt-4">
                    @foreach ($this->uploads as $video)
                        @include('pages.panel.partials.video-review', ['video' => $video, 'discarded' => false])
                    @endforeach
                </div>
            @endif
        </div>
    </x-ui.card>
</div>

@script
<script>
    Alpine.data('ownVideoUpload', () => ({
        busy: false,
        progress: 0,
        error: null,
        warnings: [],

        async pick(event) {
            const file = event.target.files[0];
            event.target.value = '';

            if (!file) {
                return;
            }

            this.error = null;
            this.warnings = [];

            let meta;

            try {
                meta = await this.readMetadata(file);
            } catch {
                this.error = 'Não foi possível ler este vídeo. Envie um arquivo MP4 ou MOV.';

                return;
            }

            const result = await $wire.startUpload(meta.duration, meta.width, meta.height);

            if (!result.ok) {
                this.error = result.error;

                return;
            }

            this.warnings = result.warnings;
            this.busy = true;
            this.progress = 0;

            try {
                await this.put(result.upload_url, file);
                await $wire.uploadFinished();
            } catch {
                this.error = 'O envio falhou. Verifique a conexão e tente de novo — os dados do prato continuam salvos.';
            } finally {
                this.busy = false;
            }
        },

        readMetadata(file) {
            return new Promise((resolve, reject) => {
                const url = URL.createObjectURL(file);
                const probe = document.createElement('video');
                probe.preload = 'metadata';
                probe.onloadedmetadata = () => {
                    resolve({ duration: probe.duration, width: probe.videoWidth, height: probe.videoHeight });
                    URL.revokeObjectURL(url);
                };
                probe.onerror = () => {
                    URL.revokeObjectURL(url);
                    reject(new Error('unreadable'));
                };
                probe.src = url;
            });
        },

        put(url, file) {
            return new Promise((resolve, reject) => {
                const request = new XMLHttpRequest();
                request.open('PUT', url);
                request.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        this.progress = Math.round((e.loaded / e.total) * 100);
                    }
                };
                request.onload = () => (request.status >= 200 && request.status < 300 ? resolve() : reject());
                request.onerror = reject;
                request.send(file);
            });
        },
    }));
</script>
@endscript
