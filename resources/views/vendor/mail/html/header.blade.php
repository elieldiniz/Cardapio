@props(['url'])
{{-- Brand symbol + wordmark, embedded as images: e-mail clients ignore web fonts.
     The styled alt text keeps the name visible when a client blocks images (e.g. Outlook's junk folder). --}}
<table class="brand" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="padding-right: 10px;">
<a href="{{ $url }}" style="text-decoration: none;">
<img src="cid:symbol" class="brand-symbol" width="35" height="40" alt="">
</a>
</td>
<td><a href="{{ $url }}" style="text-decoration: none;"><img src="cid:wordmark-light" class="wordmark" width="110" height="39" alt="{{ config('app.name') }}" style="color: #ffffff; font-family: Georgia, 'Times New Roman', serif; font-size: 26px; font-style: italic; line-height: 39px;"></a></td>
</tr>
</table>
