@props(['items' => []])
{{-- Numbered next steps: ['Title' => 'Description', ...]. --}}
<table class="steps" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($items as $title => $description)
<tr>
<td class="step-number" valign="top"><span>{{ $loop->iteration }}</span></td>
<td class="step-text" valign="top"><strong>{{ $title }}</strong><br>{{ $description }}</td>
</tr>
@endforeach
</table>
