@props(['tone' => 'accent', 'title' => null])
<table class="callout callout-{{ $tone }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="callout-content">
@if ($title)
<p class="callout-title">{{ $title }}</p>
@endif
{{ Illuminate\Mail\Markdown::parse($slot) }}
</td>
</tr>
</table>
