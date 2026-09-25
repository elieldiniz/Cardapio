@props(['title' => null])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @include('partials.favicons')
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center px-4 py-10">
    <main class="w-full max-w-[420px] rounded-2xl bg-white p-6 text-center shadow-[0_1px_3px_rgba(0,0,0,0.06)] sm:p-8">
        {{ $slot }}
    </main>
</body>
</html>
