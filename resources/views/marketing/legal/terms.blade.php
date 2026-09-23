<x-marketing.legal-page title="Termos e Condições de Uso">
    <p>Estes Termos regulam o uso do {{ config('app.name') }} (“plataforma”) por restaurantes e seus responsáveis (“você”). Ao criar uma conta, você concorda com eles.</p>

    <h2>1. O serviço</h2>
    <p>A plataforma permite publicar um cardápio digital em vídeo, acessado pelos clientes do restaurante por QR Code. Inclui cadastro de pratos, geração de vídeos por inteligência artificial a partir de fotos, envio de vídeos próprios, QR Code, personalização visual e métricas de visualização. A plataforma não realiza pedidos nem pagamentos entre o restaurante e seus clientes.</p>

    <h2>2. Conta</h2>
    <ul>
        <li>Você deve informar dados verdadeiros e confirmar o seu e-mail.</li>
        <li>Você é responsável pela senha e por tudo o que for feito na sua conta.</li>
        <li>Cada conta corresponde a um restaurante.</li>
    </ul>

    <h2>3. Planos, gerações e pagamento</h2>
    <ul>
        <li>O plano Grátis tem limites de pratos e uma quantidade única de gerações de vídeo por IA.</li>
        <li>Os planos pagos são cobrados mensalmente no cartão de crédito, pela Stripe, e renovam automaticamente até o cancelamento.</li>
        <li>Cada vídeo gerado por IA consome uma geração. As gerações do plano renovam a cada ciclo e não se acumulam. Gerações de pacotes avulsos não expiram e são usadas depois das do plano. Gerações que falharem por erro do provedor de IA não são descontadas.</li>
        <li>Preços, limites e recursos podem mudar; mudanças de preço serão comunicadas com antecedência e valem a partir do ciclo seguinte.</li>
        <li>Se o pagamento falhar e não for regularizado, a conta volta ao plano Grátis e os pratos acima do limite ficam ocultos até a regularização.</li>
    </ul>

    <h2>4. Seu conteúdo</h2>
    <p>Fotos, vídeos, textos e marcas enviados continuam sendo seus. Você nos autoriza a armazená-los, processá-los (inclusive por provedores de inteligência artificial e de hospedagem de vídeo) e exibi-los no seu cardápio, apenas para prestar o serviço. Você declara ter os direitos sobre o que envia.</p>
    <p>Os vídeos gerados por IA representam o prato de forma ilustrativa. Cabe a você aprovar apenas vídeos que representem o prato de forma fiel.</p>

    <h2>5. Uso aceitável</h2>
    <p>É proibido enviar conteúdo ilegal, ofensivo, enganoso ou que viole direitos de terceiros. Podemos remover conteúdo impróprio e suspender contas que violem estes Termos.</p>

    <h2>6. Cancelamento e exclusão</h2>
    <p>Você pode cancelar a assinatura a qualquer momento pelo painel, sem multa. Também pode excluir a conta em <strong>Minha conta</strong>: o cardápio sai do ar e a assinatura é cancelada; os dados ficam guardados por um período para eventual restauração e depois são apagados definitivamente. Veja a <a href="{{ route('legal.refund') }}">Política de Reembolso</a> e a <a href="{{ route('legal.privacy') }}">Política de Privacidade</a>.</p>

    <h2>7. Disponibilidade e responsabilidade</h2>
    <p>Trabalhamos para manter a plataforma disponível, mas podem ocorrer interrupções, inclusive de serviços de terceiros (pagamentos, vídeo, inteligência artificial). Na extensão permitida em lei, não nos responsabilizamos por lucros cessantes ou danos indiretos.</p>

    <h2>8. Alterações e foro</h2>
    <p>Estes Termos podem ser atualizados; a versão vigente fica nesta página. Aplica-se a legislação brasileira, e fica eleito o foro do domicílio do consumidor, quando aplicável.</p>
</x-marketing.legal-page>
