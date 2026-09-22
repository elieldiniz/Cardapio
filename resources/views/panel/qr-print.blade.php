{{-- Printable table display (US-6.2): A6 card with logo, name, QR and optional table number. --}}
<!DOCTYPE html>
<html lang="pt-BR" style="--accent: {{ $restaurant->accentColor() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Display de mesa · {{ $restaurant->name }}</title>
    @fonts
    <style>
        @page { size: A6; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DM Sans', system-ui, sans-serif; color: #1a1815; background: #ece9e4; }
        .card { width: 105mm; height: 148mm; margin: 12px auto; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5mm; padding: 10mm; text-align: center; border-top: 6mm solid var(--accent); }
        .logo { width: 20mm; height: 20mm; border-radius: 50%; object-fit: cover; }
        .name { font: 400 22pt/1.1 'DM Serif Display', serif; margin: 0; }
        .qr { width: 58mm; height: 58mm; }
        .qr svg { width: 100%; height: 100%; }
        .cta { font-weight: 700; font-size: 11pt; margin: 0; }
        .sub { font-size: 9pt; color: rgba(20, 18, 16, .6); margin: 0; }
        .table { font-weight: 700; font-size: 12pt; color: var(--accent); }
        @media print { body { background: #fff; } .card { margin: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="card">
        @if ($restaurant->logo_path)
            <img class="logo" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($restaurant->logo_path) }}" alt="">
        @endif
        <h1 class="name">{{ $restaurant->name }}</h1>
        <div class="qr" data-qr>{!! $svg !!}</div>
        <p class="cta">Aponte a câmera e veja o cardápio em vídeo</p>
        <p class="sub">Sem app, sem cadastro. Peça ao garçom.</p>
        @if ($table)
            <span class="table">Mesa {{ $table }}</span>
        @endif
    </div>
</body>
</html>
