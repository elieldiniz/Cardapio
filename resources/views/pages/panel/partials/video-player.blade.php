@if ($video->mux_playback_id)
    <video
        class="aspect-[9/16] w-[150px] rounded-xl bg-night object-cover"
        src="{{ \App\Support\MuxUrls::mp4($video->mux_playback_id) }}"
        @if ($video->cover_path) poster="{{ $video->cover_path }}" @endif
        controls
        muted
        loop
        playsinline
        preload="none"
    ></video>
@else
    <div class="flex aspect-[9/16] w-[150px] flex-col items-center justify-center gap-2 rounded-xl bg-night/90 px-3 text-center text-[12px] font-semibold text-white/70">
        <span class="size-5 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
        Processando vídeo…
    </div>
@endif
