<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt-BR">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: calc(100% - 24px) !important;
}

.footer {
width: 100% !important;
}

.hero-cell,
.content-cell {
padding-left: 24px !important;
padding-right: 24px !important;
}

.hero-title {
font-size: 30px !important;
}
}

@media only screen and (max-width: 500px) {
.button {
display: block !important;
text-align: center !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

@isset($preheader)
{{-- Inbox preview line, shown next to the subject and hidden in the message. --}}
<div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">{{ $preheader }}&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
@endisset

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">

<!-- Card -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Dark hero: brand + what happened -->
<tr>
<td class="hero-cell" bgcolor="#161412">
{!! $header ?? '' !!}
{!! $hero ?? '' !!}
</td>
</tr>
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
