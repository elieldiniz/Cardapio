{{-- One variation / uploaded video with its review state (US-3.3, US-3.5). --}}
@php
    $slug = $video->status->slug;
    $isActive = $this->dish->active_video_id === $video->id;
    $refusedUpload = $video->generation_id === null && $slug === 'rejeitado'
        && ($video->duration_seconds === null || \App\Actions\Videos\StartOwnVideoUpload::durationProblem($video->duration_seconds) !== null);
    $label = match (true) {
        $isActive => ['Ativo no cardápio', 'bg-success/12 text-success'],
        $slug === 'processando' => ['Processando', 'bg-ink/8 text-ink/60'],
        $refusedUpload => ['Recusado'.($video->duration_seconds ? ' · '.$video->duration_seconds.'s' : ''), 'bg-danger/12 text-danger'],
        $slug === 'rejeitado' => ['Substituído', 'bg-ink/8 text-ink/50'],
        $discarded && $slug === 'aguardando_aprovacao' => ['Descartada', 'bg-ink/8 text-ink/50'],
        $slug === 'aguardando_aprovacao' => ['Aguardando aprovação', 'bg-accent/12 text-accent'],
        default => [$video->status->name, 'bg-ink/8 text-ink/60'],
    };
@endphp

<div wire:key="video-{{ $video->id }}" class="flex w-[150px] flex-col gap-2 {{ ($discarded && ! $isActive) ? 'opacity-60' : '' }}" data-video="{{ $video->id }}">
    @include('pages.panel.partials.video-player', ['video' => $video])
    <span class="w-fit rounded-full px-2.5 py-0.5 text-[10.5px] font-bold tracking-wide uppercase {{ $label[1] }}">{{ $label[0] }}</span>
    @if ($slug === 'aguardando_aprovacao' && ! $isActive)
        <x-ui.button size="sm" wire:click="approve({{ $video->id }})" wire:confirm="Publicar este vídeo no cardápio? Ele substitui o vídeo atual.">Aprovar</x-ui.button>
    @endif
</div>
