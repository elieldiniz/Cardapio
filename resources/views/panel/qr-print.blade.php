{{-- Printable table display (US-6.2): A6 card with the dono's chosen model, colors and texts. --}}
<!DOCTYPE html>
<html lang="pt-BR" style="--accent: {{ $restaurant->accentColor() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Display de mesa · {{ $restaurant->name }}</title>
    @include('partials.favicons')
    @fonts
    @if (($font = $settings['display_font']) && ($slug = config('feed.fonts')[$font] ?? null))
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ $slug }}&display=swap">
    @endif
    <style>
        @page { size: A6; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #ece9e4; }
        .td { margin: 12px auto; }
        @media print { body { background: #fff; } .td { margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
    @include('panel.partials.table-display-css')
</head>
<body onload="window.print()">
    @include('panel.partials.table-display', ['restaurant' => $restaurant, 'svg' => $svg, 'settings' => $settings, 'table' => $table])
</body>
</html>
