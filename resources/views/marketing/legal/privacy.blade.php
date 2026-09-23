<x-marketing.legal-page title="Política de Privacidade">
    <p>Esta Política explica como o {{ config('app.name') }} trata dados pessoais, de acordo com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018 — LGPD).</p>

    <h2>1. Dados que tratamos</h2>
    <ul>
        <li><strong>Donos e equipes de restaurantes:</strong> nome, e-mail, senha (guardada de forma criptografada), nome e dados do restaurante, conteúdos enviados (fotos, vídeos, logo, textos), registros de acesso (endereço IP, navegador, aparelhos conectados).</li>
        <li><strong>Pagamentos:</strong> processados pela Stripe. Não armazenamos o número do cartão; recebemos apenas o status das cobranças e das assinaturas.</li>
        <li><strong>Clientes que abrem o cardápio:</strong> não pedimos cadastro. Registramos, de forma agregada e sem identificação pessoal, quais pratos foram vistos e por quanto tempo, usando um identificador de sessão anônimo guardado no navegador.</li>
    </ul>

    <h2>2. Para que usamos</h2>
    <ul>
        <li>Prestar o serviço: publicar o cardápio, gerar e hospedar os vídeos, mostrar métricas ao restaurante.</li>
        <li>Cobrança das assinaturas e pacotes.</li>
        <li>Segurança da conta, prevenção a fraudes e suporte.</li>
        <li>Comunicações sobre o serviço (confirmação de e-mail, vídeo pronto, avisos de cobrança).</li>
    </ul>

    <h2>3. Com quem compartilhamos</h2>
    <p>Somente com fornecedores necessários para operar o serviço, que tratam os dados em nosso nome: processamento de pagamentos (Stripe), hospedagem e entrega de vídeo (Mux), geração de vídeo por inteligência artificial, hospedagem da aplicação e envio de e-mails. Alguns desses fornecedores podem armazenar dados fora do Brasil, com salvaguardas adequadas. Não vendemos dados pessoais.</p>

    <h2>4. Por quanto tempo</h2>
    <p>Enquanto a conta existir. Ao excluir a conta, os dados vão para uma área de retenção por um período para eventual restauração e, depois, são apagados definitivamente, exceto o que precisarmos manter por obrigação legal (como registros fiscais e de acesso).</p>

    <h2>5. Seus direitos</h2>
    <p>Você pode pedir confirmação de tratamento, acesso, correção, anonimização, portabilidade e exclusão dos seus dados, além de informações sobre compartilhamento. Muitos desses pedidos podem ser feitos direto no painel, em <strong>Minha conta</strong>; os demais, pelos nossos <a href="{{ route('marketing.contact') }}">canais de contato</a>.</p>

    <h2>6. Segurança</h2>
    <p>Usamos conexões criptografadas, senhas com hash, controle de acesso por perfil e registros de auditoria para ações administrativas.</p>

    <h2>7. Cookies</h2>
    <p>Veja a <a href="{{ route('legal.cookies') }}">Política de Cookies</a>.</p>
</x-marketing.legal-page>
