@props(['eyebrow' => null, 'title', 'tone' => 'accent'])
@php
    $toneColor = ['accent' => '#ff6b3d', 'danger' => '#ef6a5e', 'success' => '#3fc48a'][$tone] ?? '#ff6b3d';
@endphp
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top: 32px;">
@if ($eyebrow)
<tr>
<td style="padding-bottom: 14px;">
<span class="eyebrow" style="color: {{ $toneColor }}; border-color: {{ $toneColor }};">● {{ $eyebrow }}</span>
</td>
</tr>
@endif
<tr>
<td class="hero-title">{{ $title }}</td>
</tr>
@if (trim($slot) !== '')
<tr>
<td class="hero-lead">{{ $slot }}</td>
</tr>
@endif
</table>
