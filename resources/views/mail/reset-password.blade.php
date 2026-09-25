<x-mail::message>
<x-slot:preheader>Use o link em até {{ $minutes }} minutos para criar uma nova senha.</x-slot:preheader>
<x-slot:hero>
<x-mail::hero eyebrow="Segurança da conta" title="Vamos criar uma nova senha.">
Recebemos um pedido para redefinir a senha da sua conta no {{ config('app.name') }}.
</x-mail::hero>
</x-slot:hero>

Olá, **{{ $name }}**!

É só clicar no botão e escolher uma senha nova. Em menos de um minuto você está de volta ao painel.

<x-mail::button :url="$url">
Criar nova senha
</x-mail::button>

<x-mail::callout tone="neutral" title="Não foi você?">
Ignore este e-mail. Sua senha atual continua valendo e ninguém entra na sua conta sem ela. O link expira em **{{ $minutes }} minutos**.
</x-mail::callout>

<x-slot:subcopy>
Se o botão não funcionar, copie e cole este endereço no navegador: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
