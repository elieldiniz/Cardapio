<?php

use App\Actions\Ai\RequestVideoGeneration;
use App\Actions\Videos\ApproveVideo;
use App\Actions\Videos\DeleteVideo;
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

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newPhotos = [];

    /**
     * One photo per upload: the S3 temporary upload driver (Laravel Cloud)
     * refuses multi-file uploads, so the browser sends the picked files one by one.
     *
     * @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null
     */
    public $photoUpload = null;

    public bool $showGenerateForm = false;

    /**
     * Optional cover for the next own video, saved as soon as it is picked (the server sets this path, never the browser).
     */
    #[Locked]
    public ?string $pendingCover = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $coverUpload = null;

    /** The "remove from the cardápio" confirmation window is open. Nothing is removed before it is confirmed. */
    public bool $confirmingRemoval = false;

    /** The style (preset) picked and the text that goes with it: the dono can edit it or write their own. */
    public ?int $presetId = null;

    public string $prompt = '';

    public function mount(Dish $dish): void
    {
        abort_unless($dish->restaurant_id === auth()->user()->restaurant_id, 404);

        $this->dishId = $dish->id;
        // The slot always opens empty: a photo only appears once the dono uploads it, and it is cleared when the video starts.
        $this->selectedPhotoIds = [];
        $this->showGenerateForm = $dish->videoGenerations()->doesntExist() && $dish->videos()->doesntExist();

        $default = RequestVideoGeneration::availablePresets()->first();
        $this->presetId = $default?->id;
        $this->prompt = $default?->prompt ?? '';
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
    public function presets()
    {
        return RequestVideoGeneration::availablePresets();
    }

    public function choosePreset(int $id): void
    {
        $preset = $this->presets->firstWhere('id', $id);

        if ($preset === null) {
            return;
        }

        $this->presetId = $preset->id;
        $this->prompt = $preset->prompt;
    }

    public function writeOwn(): void
    {
        $this->prompt = '';
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

    public function updatedPhotoUpload(): void
    {
        $this->validate(['photoUpload' => ['image', 'max:8192']], [
            'photoUpload.image' => 'Envie apenas imagens.',
            'photoUpload.max' => 'Cada foto pode ter no máximo 8 MB.',
        ]);

        $this->newPhotos = [$this->photoUpload];
        $this->reset('photoUpload');
        $this->uploadPhotos();
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

            // The video is made from one photo: the latest upload replaces the chosen one.
            $this->selectedPhotoIds = [$photo->id];
        }

        $this->reset('newPhotos');
        unset($this->dish);
    }

    public function generate(RequestVideoGeneration $request): void
    {
        $this->validate([
            'selectedPhotoIds' => ['required', 'array', 'min:1', 'max:'.RequestVideoGeneration::MAX_PHOTOS],
        ], [
            'selectedPhotoIds.required' => 'Escolha a foto do prato.',
            'selectedPhotoIds.min' => 'Escolha a foto do prato.',
            'selectedPhotoIds.max' => 'Escolha apenas 1 foto.',
        ]);

        $normalize = fn (?string $text) => trim((string) preg_replace('/\s+/u', ' ', (string) $text));
        $text = $normalize($this->prompt);
        $preset = $this->presets->firstWhere('id', $this->presetId);

        if ($text === '') {
            $this->addError('prompt', 'Escolha um estilo ou escreva como você quer o vídeo.');

            return;
        }

        // Untouched style text means "use the preset as registered"; anything else is the dono's own text.
        $customPrompt = $preset !== null && $text === $normalize($preset->prompt) ? null : $text;

        if ($customPrompt !== null && (mb_strlen($customPrompt) < RequestVideoGeneration::PROMPT_MIN || mb_strlen($customPrompt) > RequestVideoGeneration::PROMPT_MAX)) {
            $this->addError('prompt', 'Descreva o vídeo com '.RequestVideoGeneration::PROMPT_MIN.' a '.RequestVideoGeneration::PROMPT_MAX.' caracteres.');

            return;
        }

        try {
            $request->handle($this->dish, $this->selectedPhotoIds, RequestVideoGeneration::MIN_VARIATIONS, $this->presetId, $customPrompt);
        } catch (InsufficientGenerationBalanceException) {
            $this->addError('balance', 'Saldo insuficiente para esta geração.');

            return;
        } catch (VideoGenerationUnavailableException|InvalidArgumentException $exception) {
            $this->addError('selectedPhotoIds', $exception->getMessage());

            return;
        }

        // The photo was used: the slot is empty again, ready for the next one.
        $this->selectedPhotoIds = [];
        $this->showGenerateForm = false;
        unset($this->generations, $this->available, $this->restaurant, $this->dish);

        $this->dispatch('toast', message: 'Geração na fila! Avisaremos quando os vídeos estiverem prontos.');
    }

    public function updatedCoverUpload(): void
    {
        $this->validate(['coverUpload' => ['image', 'max:8192']], [
            'coverUpload.image' => 'Envie apenas imagens.',
            'coverUpload.max' => 'A capa pode ter no máximo 8 MB.',
        ]);

        $this->discardPendingCover();
        $this->pendingCover = $this->coverUpload->store("video-covers/{$this->dish->restaurant_id}", 'public');
        $this->reset('coverUpload');
    }

    public function removeCover(): void
    {
        $this->discardPendingCover();
    }

    private function discardPendingCover(): void
    {
        if ($this->pendingCover !== null) {
            Storage::disk('public')->delete($this->pendingCover);
            $this->pendingCover = null;
        }
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
            $result = $startUpload->handle($this->dish, $duration, $width, $height, $this->pendingCover);
        } catch (OwnVideoRejectedException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }

        // The cover now belongs to that video: the next one starts without one.
        $this->pendingCover = null;
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

    /**
     * Takes the live video off the cardápio and deletes it: the dish goes back to "no video".
     */
    public function removeLive(DeleteVideo $deleteVideo): void
    {
        // Only after the dono confirmed in the window: a bare call never removes anything.
        if (! $this->confirmingRemoval) {
            return;
        }

        $this->confirmingRemoval = false;

        $video = Video::query()->with('status')->where('dish_id', $this->dishId)->whereKey($this->dish->active_video_id)->first();

        if ($video === null) {
            return;
        }

        try {
            $deleteVideo->handle($video, unpublish: true);
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');

            return;
        }

        unset($this->dish, $this->generations, $this->uploads);

        $this->dispatch('toast', message: 'Vídeo removido do cardápio.');
    }

    public function deleteVideo(int $videoId, DeleteVideo $deleteVideo): void
    {
        $video = Video::query()->with('status')->where('dish_id', $this->dishId)->findOrFail($videoId);

        try {
            $deleteVideo->handle($video);
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');

            return;
        }

        unset($this->dish, $this->generations, $this->uploads);

        $this->dispatch('toast', message: 'Vídeo excluído.');
    }
};
?>

@php
    $balance = $this->restaurant->generationBalance;
    $latestGeneration = $this->generations->first();
    $active = $this->dish->activeVideo;
    $canCover = $this->available >= RequestVideoGeneration::MIN_VARIATIONS;
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
                    <x-ui.button
                        size="sm"
                        variant="danger"
                        wire:click="$set('confirmingRemoval', true)"
                        class="mt-2 w-fit border border-danger/40"
                        data-remove-live
                    >
                        <x-ui.icon name="trash" class="size-4" />
                        Remover do cardápio
                    </x-ui.button>
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
                    <strong class="text-ink">Escolha a melhor foto do prato:</strong> bem iluminada, com o prato inteiro à mostra. O vídeo é criado a partir dela e ela também vira a <strong class="text-ink">capa do vídeo</strong> no cardápio.
                </div>

                @php $chosen = $this->dish->photos->firstWhere('id', $selectedPhotoIds[0] ?? null); @endphp
                <div data-photo-slot>
                    <p class="mb-2 text-[12px] font-semibold text-ink/60">Foto do prato (vira a capa do vídeo)</p>
                    <div class="flex flex-wrap items-center gap-4">
                        {{-- One slot: the chosen photo. Clicking it opens the picker, and a new upload replaces it. --}}
                        <label class="relative flex size-32 cursor-pointer overflow-hidden rounded-xl bg-ink/[0.06] ring-2 ring-accent transition hover:brightness-95" data-photo-replace>
                            @if ($chosen)
                                <img src="{{ Storage::disk('public')->url($chosen->file_path) }}" alt="Foto escolhida do prato" class="size-full object-cover">
                                <span class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 bg-black/60 py-1.5 text-[12px] font-bold text-white"><x-ui.icon name="upload" class="size-3.5" />Trocar foto</span>
                            @else
                                <span class="flex size-full flex-col items-center justify-center gap-1.5 text-[12.5px] font-semibold text-ink/55"><x-ui.icon name="upload" />Enviar foto</span>
                            @endif
                            <x-panel.photo-picker :multiple="false" />
                        </label>

                        <div class="flex flex-col gap-1.5 text-[13px]">
                            @if ($chosen)
                                <span class="text-ink/55">Clique na foto para trocar por outra.</span>
                            @endif
                        </div>
                    </div>

                    <div wire:loading wire:target="photoUpload" class="mt-2 text-[12.5px] text-ink/55">Enviando foto…</div>
                    @error('selectedPhotoIds') <p class="mt-2 text-[12px] text-danger">{{ $message }}</p> @enderror
                    @error('newPhotos.*') <p class="mt-2 text-[12px] text-danger">{{ $message }}</p> @enderror
                    @error('photoUpload') <p class="mt-2 text-[12px] text-danger">{{ $message }}</p> @enderror
                </div>

                <div data-style-picker>
                    <p class="mb-2 text-[12px] font-semibold text-ink/60">Estilo do vídeo</p>
                    <div class="grid gap-4 md:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                        <div class="flex flex-wrap content-start gap-2 md:flex-col">
                            @foreach ($this->presets as $style)
                                <button
                                    type="button"
                                    wire:key="style-{{ $style->id }}"
                                    wire:click="choosePreset({{ $style->id }})"
                                    aria-pressed="{{ $presetId === $style->id ? 'true' : 'false' }}"
                                    class="rounded-xl border px-3.5 py-2.5 text-left text-[13.5px] font-bold transition {{ $presetId === $style->id ? 'border-accent bg-accent/10 text-accent' : 'border-ink/15 text-ink/75 hover:border-accent/50' }}"
                                >{{ $style->name }}</button>
                            @endforeach
                            <button type="button" wire:click="writeOwn" data-write-own class="rounded-xl border-2 border-dashed border-ink/20 px-3.5 py-2.5 text-left text-[13.5px] font-bold text-ink/60 transition hover:border-accent hover:text-accent">✎ Escrever o meu</button>
                        </div>

                        <div>
                            <label for="prompt" class="mb-1.5 block text-[12px] font-semibold text-ink/60">Como você quer o vídeo?</label>
                            <x-ui.textarea id="prompt" wire:model="prompt" rows="6" maxlength="{{ RequestVideoGeneration::PROMPT_MAX }}" placeholder="Ex.: a câmera se aproxima devagar do prato" />
                            <p class="mt-1.5 text-[12px] leading-relaxed text-ink/50" data-style-hint>Não exagere: descreva só o movimento e mantenha o prato como ele realmente é. Assim o cliente sente o sabor mais de perto.</p>
                            @error('prompt') <p class="mt-1.5 text-[12px] text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-1 rounded-xl border border-ink/10 p-3.5 text-[13.5px]" data-balance-summary>
                    <span>Esta geração vai consumir <strong data-consumption>1 geração</strong>.</span>
                    <span class="text-ink/60">
                        Saldo disponível: <strong class="text-ink">{{ $this->available }}</strong>
                        (mensal {{ $balance?->monthly_balance ?? 0 }} + avulso {{ $balance?->addon_balance ?? 0 }}{{ $this->restaurant->reservedGenerations() ? ', '.$this->restaurant->reservedGenerations().' reservadas em gerações na fila' : '' }}).
                    </span>
                    <span class="text-[12.5px] text-ink/50">O saldo só é debitado quando os vídeos ficam prontos. Se o provedor falhar, nada é cobrado.</span>
                </div>

                @if (! $canCover)
                    <div class="flex flex-col gap-2.5 rounded-xl bg-danger/8 p-3.5 text-[13.5px] text-ink/75" role="alert" data-balance-blocked>
                        <span><strong class="text-danger">Saldo insuficiente.</strong> Você precisa de 1 e tem {{ $this->available }} disponíveis.</span>
                        <div class="flex flex-wrap gap-2">
                            @if (Route::has('panel.subscription'))
                                <x-ui.button size="sm" :href="route('panel.subscription')" wire:navigate>Fazer upgrade de plano</x-ui.button>
                                <x-ui.button size="sm" variant="secondary" :href="route('panel.subscription').'#pacotes'" wire:navigate>Comprar pacote de gerações</x-ui.button>
                            @endif
                        </div>
                    </div>
                @endif
                @error('balance') <p class="text-[12px] text-danger">{{ $message }}</p> @enderror

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
                // The clip is made, but a round is only "pronto" for the dono once its videos finished processing.
                $finishing = $generation->status->slug === 'pronto' && $generation->videos->contains(fn ($video) => $video->status->slug === 'processando');
            @endphp
            <div wire:key="generation-{{ $generation->id }}" class="mt-5 border-t border-ink/[0.06] pt-4 first:mt-4" data-generation="{{ $generation->id }}">
                <div class="mb-3 flex flex-wrap items-center gap-2 text-[13px]">
                    <span class="font-bold text-ink">{{ $isLatest ? 'Rodada mais recente' : 'Rodada anterior' }}</span>
                    <span class="text-ink/45">· {{ $generation->created_at->format('d/m H:i') }} · {{ $generation->variations_requested }} {{ $generation->variations_requested === 1 ? 'vídeo' : 'vídeos' }} · {{ $generation->photos->count() }} {{ $generation->photos->count() === 1 ? 'foto' : 'fotos' }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase {{ $generation->status->slug === 'erro' ? 'bg-danger/12 text-danger' : 'bg-ink/8 text-ink/60' }}">{{ $finishing ? 'Finalizando…' : ($statusLabels[$generation->status->slug] ?? $generation->status->name) }}</span>
                </div>

                @if ($generation->status->slug === 'erro')
                    <p class="text-[13px] text-ink/60">Não conseguimos gerar o vídeo nesta rodada. Nenhuma geração foi descontada do seu saldo.</p>
                @elseif (! $generation->isFinished())
                    <x-ui.skeleton rows="1" avatar />
                @endif

                @php $roundVideos = $generation->videos->reject(fn ($video) => $video->id === $this->dish->active_video_id); @endphp

                @if ($generation->status->slug === 'pronto' && $generation->videos->isEmpty())
                    <p class="text-[13px] text-ink/50">O vídeo desta rodada foi excluído.</p>
                @elseif ($generation->videos->isNotEmpty() && $roundVideos->isEmpty())
                    <p class="text-[13px] text-ink/50">O vídeo desta rodada está no ar (veja acima).</p>
                @endif

                <div class="flex flex-wrap gap-4">
                    @foreach ($roundVideos as $video)
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

            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-ink/10 p-3" data-own-cover>
                @if ($pendingCover)
                    <img src="{{ Storage::disk('public')->url($pendingCover) }}" alt="Capa escolhida" class="h-16 w-11 rounded-md object-cover ring-1 ring-ink/15">
                @endif
                <div class="flex flex-col gap-1 text-[13px]">
                    <strong class="text-ink">Capa do vídeo (opcional)</strong>
                    <span class="text-ink/55">{{ $pendingCover ? 'Será a capa do próximo vídeo que você enviar.' : 'Uma foto do prato que aparece antes do vídeo e na grade do cardápio. Sem ela, usamos um quadro do vídeo.' }}</span>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    @if ($pendingCover)
                        <button type="button" wire:click="removeCover" class="text-[12.5px] font-semibold text-ink/50 hover:text-danger">Remover</button>
                    @endif
                    <label class="cursor-pointer rounded-[9px] border border-ink/15 px-3.5 py-1.5 text-[12.5px] font-bold text-ink/70 hover:bg-ink/5">
                        {{ $pendingCover ? 'Trocar' : 'Escolher capa' }}
                        <x-panel.photo-picker model="coverUpload" :multiple="false" />
                    </label>
                </div>
            </div>
            @error('coverUpload') <p class="text-[12px] text-danger">{{ $message }}</p> @enderror
            <div wire:loading wire:target="coverUpload" class="text-[12.5px] text-ink/55">Enviando capa…</div>

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

            @php $otherUploads = $this->uploads->reject(fn ($video) => $video->id === $this->dish->active_video_id); @endphp
            @if ($otherUploads->isNotEmpty())
                <div class="flex flex-wrap gap-4 border-t border-ink/[0.06] pt-4">
                    @foreach ($otherUploads as $video)
                        @include('pages.panel.partials.video-review', ['video' => $video, 'discarded' => false])
                    @endforeach
                </div>
            @endif
        </div>
    </x-ui.card>

    {{-- Confirmation before taking the live video off the cardápio. --}}
    @if ($active)
        <x-ui.modal wire:model="confirmingRemoval" title="Remover este vídeo?">
            <p class="text-[14px] leading-relaxed text-ink/70">O vídeo sai do cardápio e é apagado de vez. O prato deixa de aparecer no feed até você aprovar outro vídeo. <strong class="text-ink">Não dá para desfazer.</strong></p>
            <x-slot:footer>
                <x-ui.button variant="secondary" class="flex-1" wire:click="$set('confirmingRemoval', false)" data-cancel-remove>Cancelar</x-ui.button>
                <x-ui.button class="flex-1 !bg-danger" wire:click="removeLive" wire:loading.attr="disabled" wire:target="removeLive" data-confirm-remove>Sim, remover</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
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
