# Degustou Cardápio — User Stories

<!-- inputs: project-description.md@sha256:de789e216b36 -->

## Overview

Degustou Cardápio é uma plataforma de cardápio digital em vídeo, estilo Reels, acessada pelo cliente via QR Code na mesa. O dono do restaurante cadastra pratos e gera vídeos (por IA a partir de fotos, ou upload próprio) num painel próprio; um super admin gerencia o sistema como um todo (planos, assinaturas, custos, moderação). O produto é só de visualização — pedido continua com o garçom.

**User Types:**
- **Cliente** - pessoa na mesa do restaurante que escaneia o QR Code e navega o feed de vídeos; não cria conta, não faz login.
- **Dono do restaurante** - responsável pela conta do restaurante; cadastra cardápio, gera/aprova vídeos, gerencia aparência, QR Code, métricas e assinatura no painel do restaurante.
- **Super admin** - opera o sistema como um todo no painel `/admin`: restaurantes, planos, assinaturas, fila de gerações, presets de IA, moderação e custos.

---

## 1. Feed do Cliente

### US-1.1: Abrir o cardápio via QR Code
**As a** cliente
**I want to** escanear o QR Code na mesa e cair direto no feed de vídeos do restaurante
**So that** eu veja os pratos sem precisar instalar app ou fazer login

**Acceptance Criteria:**
- [ ] Escanear o QR Code abre o feed direto no navegador, sem exigir instalação de app ou cadastro/login.
- [ ] A capa do primeiro prato aparece em menos de 1 segundo.
- [ ] O primeiro vídeo começa a tocar em menos de 2 segundos numa conexão 4G.
- [ ] O HTML inicial já vem do servidor com a capa e o link do primeiro vídeo embutidos, sem depender do JS carregar primeiro.

**Expected Result:** o cliente escaneia o QR Code e vê o prato em vídeo quase instantaneamente, sem qualquer fricção de instalação ou cadastro.

---

### US-1.2: Assistir ao vídeo do prato
**As a** cliente
**I want to** ver o vídeo do prato em autoplay, mudo e em loop, com opção de ativar o som
**So that** eu entenda como o prato é sem precisar interagir

**Acceptance Criteria:**
- [ ] O vídeo do prato ativo toca automaticamente, sem som, em loop contínuo.
- [ ] Existe um botão visível para ativar/desativar o som.
- [ ] Nome, preço e descrição curta do prato ficam sobrepostos ao vídeo.
- [ ] A qualidade inicial do vídeo é 480p, subindo para 720p conforme a conexão permite.

**Expected Result:** o vídeo do prato toca sozinho, sem exigir nenhum toque do cliente, com dados essenciais visíveis por cima.

---

### US-1.3: Navegar entre pratos e categorias
**As a** cliente
**I want to** rolar verticalmente entre pratos e trocar de categoria pela barra fixa no topo
**So that** eu explore o cardápio inteiro rapidamente

**Acceptance Criteria:**
- [ ] Rolar a tela verticalmente (scroll-snap) avança para o próximo prato da categoria atual.
- [ ] A barra de categorias fica fixa no topo e permite rolagem lateral quando há muitas categorias.
- [ ] Tocar numa categoria troca o conjunto de vídeos exibidos e volta ao primeiro prato dessa categoria.
- [ ] A troca de prato/categoria é percebida como instantânea (sem tela de carregamento visível).

**Expected Result:** o cliente navega o cardápio inteiro só com rolagem e toques na barra de categorias, sem recarregar a página.

---

### US-1.4: Ver detalhe completo, selos e status do prato
**As a** cliente
**I want to** abrir o detalhe completo de um prato e ver selos ou aviso de esgotado
**So that** eu tenha informação suficiente para decidir o que pedir ao garçom

**Acceptance Criteria:**
- [ ] Um painel sobe de baixo mostrando a descrição completa do prato (além da curta já visível sobre o vídeo).
- [ ] Selos configurados pelo dono (novo, mais pedido, vegetariano) aparecem sobre o card do prato.
- [ ] Um prato marcado como esgotado exibe aviso claro de indisponibilidade no feed.
- [ ] O tempo assistido e a visualização do prato são registrados para métricas do dono, sem exigir ação do cliente.

**Expected Result:** o cliente consegue ver todos os detalhes relevantes de um prato, inclusive disponibilidade, sem sair do feed.

---

## 2. Gestão de Cardápio

### US-2.1: Organizar categorias
**As a** dono do restaurante
**I want to** criar, renomear, ocultar e reordenar categorias
**So that** o cardápio no feed reflita a organização que eu quero

**Acceptance Criteria:**
- [ ] É possível criar uma nova categoria com nome.
- [ ] É possível renomear e ocultar (sem excluir) uma categoria existente.
- [ ] É possível reordenar categorias por arrastar; a ordem se reflete na barra de categorias do feed.
- [ ] Uma categoria oculta não aparece na barra de categorias do feed nem em seus pratos.

**Expected Result:** o dono controla livremente quais categorias existem, seus nomes e sua ordem de exibição no feed.

---

### US-2.2: Criar e editar prato
**As a** dono do restaurante
**I want to** cadastrar um prato com fotos, nome, preço, descrições, selos e variações
**So that** ele apareça corretamente representado no cardápio

**Acceptance Criteria:**
- [ ] O cadastro de prato exige nome, preço e categoria; descrição curta e completa, fotos e selos são opcionais no cadastro inicial.
- [ ] É possível adicionar variações do prato (ex.: tamanhos), cada uma com nome e preço próprio.
- [ ] É possível editar qualquer campo do prato depois de criado.
- [ ] Um prato recém-criado só aparece no feed do cliente depois de ter um vídeo aprovado (ver US-3.3).

**Expected Result:** o dono cadastra e mantém os dados de cada prato de forma independente do vídeo, podendo editar a qualquer momento.

---

### US-2.3: Atualizar preço e disponibilidade na hora
**As a** dono do restaurante
**I want to** mudar o preço de um prato ou marcá-lo como esgotado/oculto instantaneamente
**So that** eu não precise reimprimir nada nem deixar informação errada no ar

**Acceptance Criteria:**
- [ ] Alterar o preço de um prato reflete no feed do cliente sem necessidade de nova aprovação de vídeo.
- [ ] Marcar um prato como esgotado o mantém visível no feed, mas sinalizado como indisponível (ver US-1.4).
- [ ] Ocultar um prato o remove completamente do feed do cliente, sem apagar seus dados.
- [ ] Cada mudança de status é refletida no feed em até alguns segundos (sem exigir deploy ou cache manual).

**Expected Result:** o dono ajusta preço e disponibilidade em tempo real, sem qualquer trabalho manual fora do painel.

---

## 3. Geração de Vídeo por IA

### US-3.1: Gerar vídeo a partir de uma foto
**As a** dono do restaurante
**I want to** enviar uma foto do prato e receber um vídeo curto gerado por IA no preset padrão
**So that** eu tenha um vídeo atraente sem precisar gravar nada

**Acceptance Criteria:**
- [ ] O envio aceita no mínimo 1 foto do prato.
- [ ] O job de geração roda em fila assíncrona (o dono não fica esperando na tela).
- [ ] O preset aplicado é fixo (movimento de câmera 30–45° com leve aproximação, efeitos sutis quando fizer sentido, 5–10s, vertical 9:16); o dono não escreve prompt.
- [ ] Com apenas 1 foto, a IA não executa giro de 360° completo (evita inventar o lado de trás do prato).
- [ ] O sistema gera de 2 a 3 variações por pedido.

**Expected Result:** a partir de 1 foto, o dono recebe de 2 a 3 clipes gerados automaticamente, seguindo sempre o mesmo estilo visual.

---

### US-3.2: Gerar vídeo a partir de múltiplos ângulos
**As a** dono do restaurante
**I want to** enviar de 3 a 4 fotos em ângulos diferentes do mesmo prato
**So that** o vídeo gerado tenha um giro mais completo e realista

**Acceptance Criteria:**
- [ ] O envio aceita até 4 fotos do mesmo prato, identificadas como ângulos diferentes.
- [ ] Quando há múltiplas fotos, o preset pode aplicar um giro maior do que o permitido com 1 foto só.
- [ ] O consumo de saldo de gerações é o mesmo por variação gerada, independente de 1 ou várias fotos terem sido enviadas.
- [ ] A tela de envio recomenda explicitamente 3–4 ângulos para melhor resultado, deixando claro que 1 foto também é aceita.

**Expected Result:** o dono que envia várias fotos recebe um vídeo com giro mais convincente que o gerado a partir de 1 foto só.

---

### US-3.3: Escolher e aprovar variação gerada
**As a** dono do restaurante
**I want to** revisar as variações de vídeo geradas e aprovar uma antes de publicar
**So that** nenhum vídeo vá ao ar sem eu ter validado que representa o prato de verdade

**Acceptance Criteria:**
- [ ] O dono é avisado quando as variações ficam prontas (vídeo processado e enviado ao Mux).
- [ ] O dono visualiza cada variação antes de decidir.
- [ ] Ao aprovar uma variação, ela se torna o vídeo ativo do prato e passa a aparecer no feed do cliente.
- [ ] Nenhum vídeo gerado aparece no feed do cliente sem aprovação explícita do dono.
- [ ] Um prato tem no máximo um vídeo aprovado ativo por vez; aprovar uma nova variação substitui a anterior.

**Expected Result:** todo vídeo publicado no feed passou por aprovação manual do dono, nunca é publicado automaticamente.

---

### US-3.4: Bloqueio de geração por saldo insuficiente
**As a** dono do restaurante
**I want to** ver meu saldo de gerações antes de confirmar um pedido e ser bloqueado se ele acabar
**So that** eu não seja cobrado ou surpreendido por gerações que não posso pagar

**Acceptance Criteria:**
- [ ] Antes de confirmar a geração, o painel mostra quanto do saldo será consumido (1 geração por variação pedida).
- [ ] Se o saldo disponível (mensal + avulso) for menor que o necessário, a geração é bloqueada antes de iniciar.
- [ ] Quando bloqueado, o painel oferece upgrade de plano ou compra de pacote avulso de gerações.
- [ ] Uma geração que falha por erro do provedor de IA não consome saldo (é estornada/não debitada).

**Expected Result:** o dono nunca inicia uma geração sem saldo suficiente, e nunca perde saldo por falha do provedor.

---

### US-3.5: Regenerar vídeo
**As a** dono do restaurante
**I want to** pedir uma nova geração quando nenhuma variação me agradar
**So that** eu tenha controle total sobre o resultado final antes de publicar

**Acceptance Criteria:**
- [ ] O dono pode pedir uma nova rodada de geração para o mesmo prato a qualquer momento.
- [ ] Cada nova rodada consome saldo normalmente (mesma regra de US-3.4).
- [ ] Variações antigas não aprovadas ficam disponíveis para consulta ou são descartadas de forma clara na interface (sem ambiguidade sobre qual é a ativa).

**Expected Result:** o dono pode iterar quantas vezes seu saldo permitir até aprovar um vídeo satisfatório.

---

## 4. Vídeo Próprio

### US-4.1: Enviar vídeo próprio do prato
**As a** dono do restaurante
**I want to** enviar um vídeo que eu mesmo gravei, em vez de gerar por IA
**So that** eu use minhas próprias imagens sem gastar saldo de gerações

**Acceptance Criteria:**
- [ ] O upload vai direto do dispositivo do dono para o Mux, sem passar pela IA nem pelo servidor da aplicação.
- [ ] O envio de vídeo próprio não consome saldo de gerações.
- [ ] A tela de envio recomenda: vertical, 5 a 15 segundos, prato em destaque.
- [ ] O vídeo próprio segue o mesmo fluxo de aprovação de US-3.3 antes de aparecer no feed.

**Expected Result:** o dono publica vídeos próprios com o mesmo controle de qualidade dos vídeos gerados por IA, sem custo de geração.

---

### US-4.2: Validar vídeo fora do padrão
**As a** dono do restaurante
**I want to** ser avisado quando meu vídeo próprio não segue as recomendações
**So that** eu saiba corrigir antes de publicar um vídeo de baixa qualidade

**Acceptance Criteria:**
- [ ] Um vídeo mais longo que o recomendado é cortado ou recusado no envio, com mensagem explicando o motivo.
- [ ] Um vídeo fora do formato vertical recebe aviso antes da publicação.
- [ ] O dono pode reenviar um novo arquivo no lugar do recusado sem perder os dados já cadastrados do prato.

**Expected Result:** vídeos próprios fora do padrão são barrados ou ajustados antes de chegar ao feed do cliente.

---

## 5. Assinatura e Cobrança

### US-5.1: Começar no plano Grátis
**As a** dono do restaurante
**I want to** criar minha conta sozinho e começar a usar o produto no plano Grátis
**So that** eu experimente o produto antes de pagar qualquer coisa

**Acceptance Criteria:**
- [ ] A criação de conta é self-service, sem intervenção do super admin.
- [ ] A conta nova começa automaticamente no plano Grátis, com limite de até 15 pratos no cardápio.
- [ ] O plano Grátis concede 5 gerações de vídeo por IA no total, uma única vez (não renovam mensalmente).
- [ ] O cardápio do plano Grátis exibe a marca "feito com [produto]".

**Expected Result:** qualquer dono consegue começar a usar o produto sozinho, sem custo, com um limite claro de gerações de degustação.

---

### US-5.2: Fazer upgrade de plano
**As a** dono do restaurante
**I want to** fazer upgrade para um plano pago pelo painel
**So that** eu tenha mais pratos e gerações de vídeo disponíveis

**Acceptance Criteria:**
- [ ] O upgrade abre um Stripe Checkout em modo assinatura, cobrando em reais (BRL).
- [ ] O único meio de pagamento aceito no MVP é cartão de crédito (sem Pix ou boleto).
- [ ] Após confirmação do pagamento (via webhook), o plano do restaurante é atualizado e o saldo de gerações do novo plano é liberado.
- [ ] Limites de pratos e gerações do plano anterior deixam de valer assim que o upgrade é confirmado.

**Expected Result:** o dono conclui o upgrade sem sair do painel e passa a operar sob os novos limites imediatamente após a confirmação do pagamento.

---

### US-5.3: Comprar pacote avulso de gerações
**As a** dono do restaurante
**I want to** comprar gerações extras avulsas quando meu saldo mensal acabar
**So that** eu continue gerando vídeos sem precisar mudar de plano

**Acceptance Criteria:**
- [ ] A compra de pacote avulso usa Stripe Checkout em modo pagamento único.
- [ ] Gerações avulsas compradas não expiram.
- [ ] Gerações avulsas só são consumidas depois que o saldo mensal do plano se esgota.
- [ ] O preço e a quantidade de gerações de cada pacote vêm do cadastro feito pelo super admin (ver US-7.3), não são fixos no app.

**Expected Result:** o dono compra gerações extras a qualquer momento, e elas ficam disponíveis até serem usadas.

---

### US-5.4: Gerenciar cartão e faturas
**As a** dono do restaurante
**I want to** ver minhas faturas, trocar o cartão e cancelar a assinatura
**So that** eu controle minha cobrança sem precisar pedir ajuda ao suporte

**Acceptance Criteria:**
- [ ] O painel abre o Portal do Cliente da Stripe a partir da tela de Assinatura.
- [ ] No portal, o dono consegue ver histórico de faturas, trocar o cartão cadastrado e cancelar a assinatura.
- [ ] O painel mostra o plano atual e o uso de gerações no mês sem precisar entrar no portal da Stripe.

**Expected Result:** o dono resolve qualquer questão de cobrança (cartão, fatura, cancelamento) de forma self-service.

---

### US-5.5: Voltar ao plano Grátis por inadimplência
**As a** dono do restaurante
**I want to** ter meu cardápio mantido no ar mesmo se um pagamento falhar
**So that** eu não perca acesso ao cliente por um problema pontual de cobrança

**Acceptance Criteria:**
- [ ] Quando um pagamento falha, a Stripe tenta recobrar automaticamente por alguns dias antes de qualquer mudança de plano.
- [ ] Se o pagamento não for regularizado, o restaurante volta automaticamente ao plano Grátis (não fica suspenso nem sai do ar).
- [ ] Pratos que excedem o limite do plano Grátis ficam ocultos no feed até o dono regularizar o pagamento ou reduzir a quantidade de pratos.
- [ ] O dono é avisado no painel sobre a falha de pagamento e o motivo do downgrade.

**Expected Result:** falha de pagamento nunca tira o cardápio do ar; o pior caso é voltar aos limites do plano Grátis.

---

## 6. Aparência, QR Code e Métricas

### US-6.1: Personalizar aparência do cardápio
**As a** dono do restaurante
**I want to** definir logo, cor de destaque e fonte do meu cardápio, com prévia ao vivo
**So that** o feed pareça a cara do meu restaurante

**Acceptance Criteria:**
- [ ] É possível enviar um logo e escolher uma cor de destaque e uma fonte.
- [ ] A tela de aparência mostra uma prévia ao vivo do feed com as escolhas aplicadas antes de salvar.
- [ ] As mudanças salvas refletem no feed do cliente na próxima visita.

**Expected Result:** cada restaurante tem uma identidade visual própria no feed, configurável sem código.

---

### US-6.2: Gerar e imprimir QR Code
**As a** dono do restaurante
**I want to** gerar o QR Code do meu cardápio e um modelo de display de mesa
**So that** eu consiga colocar o acesso ao cardápio nas mesas do restaurante

**Acceptance Criteria:**
- [ ] O painel gera um QR Code único que aponta para o feed do restaurante.
- [ ] É possível baixar/imprimir o QR Code em um modelo de display pronto para mesa.
- [ ] O QR Code continua válido mesmo após o dono editar categorias, pratos ou aparência (não muda a cada edição).

**Expected Result:** o dono consegue colocar o QR Code físico nas mesas sem depender de suporte técnico.

---

### US-6.3: Ver pratos mais vistos e tempo assistido
**As a** dono do restaurante
**I want to** ver quais pratos são mais vistos e por quanto tempo
**So that** eu saiba quais pratos destacar e sinta o valor de manter o cardápio em vídeo

**Acceptance Criteria:**
- [ ] O painel mostra, por prato, número de visualizações e tempo médio assistido.
- [ ] Os dados de visualização são agregados em lote/por dia, sem impacto perceptível de performance no feed do cliente.
- [ ] O nível de detalhe das métricas (total do cardápio vs. por prato vs. por prato + tempo assistido) varia conforme o plano do restaurante.

**Expected Result:** o dono enxerga dados concretos de engajamento por prato, usados para decidir o que destacar no cardápio.

---

## 7. Administração do Sistema

### US-7.1: Ver dashboard de métricas do negócio
**As a** super admin
**I want to** ver MRR, clientes ativos por plano, novos/cancelados no mês e conversão grátis → pago
**So that** eu acompanhe a saúde do negócio de forma centralizada

**Acceptance Criteria:**
- [ ] O dashboard exibe MRR atualizado.
- [ ] O dashboard mostra clientes ativos segmentados por plano.
- [ ] O dashboard mostra quantos restaurantes entraram e cancelaram no mês corrente.
- [ ] O dashboard mostra a taxa de conversão de restaurantes do plano Grátis para um plano pago.

**Expected Result:** o super admin tem uma visão consolidada da saúde do SaaS sem precisar cruzar dados manualmente.

---

### US-7.2: Gerenciar restaurantes
**As a** super admin
**I want to** listar restaurantes, ver detalhes, suspender/reativar e entrar como o dono
**So that** eu consiga dar suporte e agir sobre contas problemáticas

**Acceptance Criteria:**
- [ ] A listagem de restaurantes mostra plano, status e uso (pratos, gerações) de cada um.
- [ ] É possível suspender e reativar um restaurante, o que bloqueia/libera o acesso ao painel do dono.
- [ ] É possível "impersonar" (entrar como) o dono de um restaurante para dar suporte.
- [ ] Toda suspensão, reativação e impersonação fica registrada em log de auditoria com usuário, ação e data.

**Expected Result:** o super admin resolve problemas de conta e dá suporte direto, com rastro auditável de cada ação sensível.

---

### US-7.3: Gerenciar planos, limites e cupons
**As a** super admin
**I want to** criar/editar planos (preço, limites, recursos) e cupons de desconto, vinculados à Stripe
**So that** eu ajuste a oferta comercial sem precisar de deploy

**Acceptance Criteria:**
- [ ] É possível criar e editar planos: nome, preço, gerações por mês, limite de pratos, recursos liberados (ex.: remoção de marca, nível de métricas).
- [ ] Cada plano é vinculado a um `stripe_price_id` correspondente na Stripe.
- [ ] É possível criar cupons de desconto, sincronizados com a Stripe.
- [ ] É possível cadastrar e editar o preço e a quantidade de gerações de pacotes avulsos (usados em US-5.3).

**Expected Result:** toda a política comercial (planos, limites, cupons, pacotes avulsos) é gerida pelo super admin sem alteração de código.

---

### US-7.4: Acompanhar fila de gerações e editar presets de IA
**As a** super admin
**I want to** ver a fila de gerações de vídeo, reprocessar erros e editar os presets de IA
**So that** eu garanta a qualidade e a operação da geração de vídeo em produção

**Acceptance Criteria:**
- [ ] A fila de gerações mostra status (fila, gerando, pronto, erro) e permite reprocessar itens com erro.
- [ ] O super admin vê o custo por geração e o custo total de IA no mês.
- [ ] É possível editar, por preset, o prompt, o movimento de câmera, a duração e o provedor de IA usado.
- [ ] A troca de provedor de IA num preset não exige alteração de código (reforça o requisito de provedor plugável).

**Expected Result:** o super admin opera e ajusta a geração de vídeo por IA de ponta a ponta, sem depender de deploy.

---

### US-7.5: Moderar conteúdo enviado
**As a** super admin
**I want to** ver fotos e vídeos enviados pelos donos e remover conteúdo impróprio
**So that** eu proteja a plataforma de conteúdo inadequado

**Acceptance Criteria:**
- [ ] O super admin acessa uma lista de fotos/vídeos enviados, filtrável por restaurante.
- [ ] É possível remover uma foto ou vídeo específico, o que o tira do feed do cliente imediatamente se estiver ativo.
- [ ] A remoção de conteúdo é registrada em log de auditoria.

**Expected Result:** o super admin consegue agir rapidamente sobre conteúdo impróprio antes ou depois de ele ir ao ar.

---

### US-7.6: Acompanhar custos vs. receita
**As a** super admin
**I want to** comparar o gasto com IA e Mux no mês contra a receita
**So that** eu saiba se a operação está saudável financeiramente

**Acceptance Criteria:**
- [ ] O painel mostra o custo total de IA (geração de vídeo) no mês corrente.
- [ ] O painel mostra o custo total de hospedagem de vídeo (Mux) no mês corrente.
- [ ] O painel compara esses custos com a receita (MRR + pacotes avulsos) do mesmo período.

**Expected Result:** o super admin identifica rapidamente se a margem está apertando antes que vire um problema financeiro.

---

## 8. Autenticação e Conta

### US-8.1: Criar conta com e-mail e senha
**As a** dono do restaurante
**I want to** criar minha conta com e-mail e senha
**So that** eu acesse o painel do restaurante e comece a usar o plano Grátis

**Acceptance Criteria:**
- [ ] O cadastro pede nome, e-mail e senha; e-mail precisa ser único no sistema.
- [ ] A senha exige no mínimo 8 caracteres.
- [ ] Ao concluir o cadastro, a conta já nasce vinculada a um restaurante no plano Grátis (ver US-5.1).
- [ ] Contas de super admin **não** são criadas por este fluxo self-service — são provisionadas diretamente (seed/admin interno).

**Expected Result:** qualquer dono cria sua própria conta e cai direto no painel do restaurante, sem intervenção manual.

---

### US-8.2: Fazer login
**As a** usuário do sistema (dono ou super admin)
**I want to** entrar com e-mail e senha
**So that** eu acesse o painel correspondente ao meu papel

**Acceptance Criteria:**
- [ ] O login aceita e-mail e senha cadastrados.
- [ ] Após autenticar, o dono é redirecionado ao painel do restaurante e o super admin à área `/admin`.
- [ ] Um usuário com papel `dono` não consegue acessar `/admin`, mesmo autenticado.
- [ ] Tentativas de login com credenciais inválidas mostram erro genérico (sem indicar se o e-mail existe ou não).

**Expected Result:** cada usuário autenticado só acessa o painel do seu próprio papel, nunca o do outro.

---

### US-8.3: Recuperar senha esquecida
**As a** usuário do sistema (dono ou super admin)
**I want to** redefinir minha senha quando esquecer
**So that** eu recupere o acesso à minha conta sem precisar de suporte

**Acceptance Criteria:**
- [ ] A tela de login oferece um link "esqueci minha senha" que pede o e-mail cadastrado.
- [ ] Um e-mail com link de redefinição é enviado; o link é válido por 60 minutos.
- [ ] Após usar o link, a nova senha substitui a antiga e todas as sessões ativas anteriores são encerradas.
- [ ] Pedir redefinição para um e-mail não cadastrado não revela se o e-mail existe (mesma mensagem de confirmação).

**Expected Result:** o usuário recupera o acesso sozinho, por e-mail, sem depender de suporte manual.

---

### US-8.4: Encerrar sessão
**As a** usuário do sistema (dono ou super admin)
**I want to** encerrar minha sessão (logout)
**So that** eu proteja minha conta em dispositivos compartilhados

**Acceptance Criteria:**
- [ ] Existe uma ação de logout visível no painel (dono e super admin).
- [ ] Ao fazer logout, a sessão é invalidada e o usuário é redirecionado para a tela de login.
- [ ] Tentar acessar uma rota protegida do painel após logout redireciona para o login.

**Expected Result:** o usuário encerra o acesso à conta a qualquer momento, de forma imediata e confiável.

---

## Appendix: User Story Status

| ID | Story | Priority | Status |
|----|-------|----------|--------|
| US-1.1 | Abrir o cardápio via QR Code | High | Pending |
| US-1.2 | Assistir ao vídeo do prato | High | Pending |
| US-1.3 | Navegar entre pratos e categorias | High | Pending |
| US-1.4 | Ver detalhe completo, selos e status do prato | High | Pending |
| US-2.1 | Organizar categorias | High | Pending |
| US-2.2 | Criar e editar prato | High | Pending |
| US-2.3 | Atualizar preço e disponibilidade na hora | High | Pending |
| US-3.1 | Gerar vídeo a partir de uma foto | High | Pending |
| US-3.2 | Gerar vídeo a partir de múltiplos ângulos | High | Pending |
| US-3.3 | Escolher e aprovar variação gerada | High | Pending |
| US-3.4 | Bloqueio de geração por saldo insuficiente | High | Pending |
| US-4.1 | Enviar vídeo próprio do prato | High | Pending |
| US-5.1 | Começar no plano Grátis | High | Pending |
| US-5.2 | Fazer upgrade de plano | High | Pending |
| US-5.4 | Gerenciar cartão e faturas | High | Pending |
| US-5.5 | Voltar ao plano Grátis por inadimplência | High | Pending |
| US-6.2 | Gerar e imprimir QR Code | High | Pending |
| US-6.3 | Ver pratos mais vistos e tempo assistido | High | Pending |
| US-7.2 | Gerenciar restaurantes | High | Pending |
| US-7.3 | Gerenciar planos, limites e cupons | High | Pending |
| US-7.4 | Acompanhar fila de gerações e editar presets de IA | High | Pending |
| US-8.1 | Criar conta com e-mail e senha | High | Pending |
| US-8.2 | Fazer login | High | Pending |
| US-8.3 | Recuperar senha esquecida | High | Pending |
| US-8.4 | Encerrar sessão | High | Pending |
| US-3.5 | Regenerar vídeo | Medium | Pending |
| US-4.2 | Validar vídeo fora do padrão | Medium | Pending |
| US-5.3 | Comprar pacote avulso de gerações | Medium | Pending |
| US-6.1 | Personalizar aparência do cardápio | Medium | Pending |
| US-7.1 | Ver dashboard de métricas do negócio | Medium | Pending |
| US-7.5 | Moderar conteúdo enviado | Medium | Pending |
| US-7.6 | Acompanhar custos vs. receita | Medium | Pending |
