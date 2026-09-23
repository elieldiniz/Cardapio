@php $contact = config('landing.contact'); @endphp
<x-marketing.legal-page title="Contato" description="Fale com a equipe do {{ config('app.name') }}.">
    <p>Tem dúvidas, sugestões ou precisa de ajuda com o seu cardápio? Fale com a gente.</p>

    @if (array_filter($contact))
        <ul class="flex flex-col gap-2" data-contact-channels>
            @if ($contact['whatsapp'])
                <li><strong>WhatsApp:</strong> <a href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener">abrir conversa</a></li>
            @endif
            @if ($contact['email'])
                <li><strong>E-mail:</strong> <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></li>
            @endif
            @if ($contact['instagram'])
                <li><strong>Instagram:</strong> <a href="https://instagram.com/{{ $contact['instagram'] }}" target="_blank" rel="noopener">{{ '@'.$contact['instagram'] }}</a></li>
            @endif
        </ul>
    @else
        <p>Se você já tem conta, fale com a gente pelo painel do restaurante. Em breve, publicaremos aqui os nossos canais de atendimento.</p>
    @endif

    <h2>Clientes do plano pago</h2>
    <p>Questões de cobrança — cartão, faturas e cancelamento — podem ser resolvidas direto no painel, em <strong>Assinatura</strong>.</p>
</x-marketing.legal-page>
