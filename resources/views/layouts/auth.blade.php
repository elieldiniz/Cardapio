<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @include('partials.favicons')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <a href="{{ url('/') }}" class="mb-6" aria-label="{{ config('app.name') }}">
        <x-brand.logo size="size-11" name-class="text-[34px] text-ink" />
    </a>

    <main class="w-full max-w-[400px] rounded-2xl bg-white p-6 shadow-[0_1px_3px_rgba(0,0,0,0.06)] sm:p-8">
        {{ $slot }}
    </main>

    <x-ui.toast />
</body>
</html>
