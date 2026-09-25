<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Cardápio indisponível · {{ $restaurant->name }}</title>
    @include('partials.favicons')
    <style>
        body { margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; background: #101010; color: #fff; font-family: system-ui, sans-serif; text-align: center; padding: 24px; box-sizing: border-box; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        p { margin: 0; color: rgba(255, 255, 255, .7); font-size: 15px; line-height: 1.5; }
    </style>
</head>
<body>
    <div>
        <h1>Cardápio indisponível no momento</h1>
        <p>O cardápio em vídeo de {{ $restaurant->name }} está temporariamente fora do ar.<br>Peça o cardápio ao garçom.</p>
    </div>
</body>
</html>
