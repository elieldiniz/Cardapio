<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <div class="mb-6 flex items-center gap-2.5">
        <span class="flex size-10 items-center justify-center rounded-full bg-accent font-serif text-xl text-white">D</span>
        <span class="font-serif text-[22px] text-ink">{{ config('app.name') }}</span>
    </div>

    <main class="w-full max-w-[400px] rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(0,0,0,0.06)] sm:p-8">
        {{ $slot }}
    </main>

    <x-ui.toast />
</body>
</html>
