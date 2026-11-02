/**
 * Checkout of the client feed (RF-06, RF-06a–d, UI-02) — three short steps inside the
 * same bottom sheet as the cart: 1 how to receive, 2 who and where, 3 payment and send.
 *
 * Nothing here is trusted: the totals shown are an estimate, the server prices the order
 * again (RF-05) and answers 422 with the unavailable items if the cart is out of date
 * (RF-06e). The customer's name, WhatsApp and address stay on the device for the next
 * order (RF-06d). The WhatsApp message comes ready from the server; the customer only
 * taps send, then confirms with "Sim, enviei" (RF-06a).
 */

const PAYMENTS = [
    ['pix', 'Pix', 'Pague pelo app do banco'],
    ['dinheiro', 'Dinheiro', 'Pode pedir troco'],
    ['debito', 'Débito', 'Maquininha na entrega'],
    ['credito', 'Crédito', 'Maquininha na entrega'],
];

export function startCheckout(ctx) {
    const { config, store, body, esc, brl, cents, subtotalCents } = ctx;
    const customerKey = `degusta-customer:${config.store_slug}`;
    const saved = readSaved(customerKey);

    const state = {
        step: 1,
        fulfillment: saved.fulfillment === 'entrega' ? 'entrega' : 'retirada',
        zoneId: saved.zoneId ?? '',
        neighborhood: saved.neighborhood ?? '',
        name: saved.name ?? '',
        phone: saved.phone ?? '',
        street: saved.street ?? '',
        number: saved.number ?? '',
        noNumber: Boolean(saved.noNumber),
        complement: saved.complement ?? '',
        reference: saved.reference ?? '',
        payment: saved.payment ?? 'pix',
        changeFor: '',
        notes: '',
        geo: null,
        geoError: null,
        coupon: sessionStorageGet('degusta-coupon') ?? '',
        promo: null,
        error: null,
        sending: false,
        order: null,
    };

    const delivery = config.delivery;
    const hasZones = delivery.mode === 'bairro' && delivery.zones.length > 0;

    // ---- Money ----

    // ---- Distance fee (RF-24a): an estimate; the server recalculates it from the same location ----

    const hasDistance = delivery.mode === 'distancia' && (delivery.tiers ?? []).length > 0 && delivery.store_lat !== null;

    function distanceKm() {
        if (!hasDistance || !state.geo) {
            return null;
        }

        const rad = (degrees) => (degrees * Math.PI) / 180;
        const dLat = rad(state.geo.lat - Number(delivery.store_lat));
        const dLng = rad(state.geo.lng - Number(delivery.store_lng));
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(rad(Number(delivery.store_lat))) * Math.cos(rad(state.geo.lat)) * Math.sin(dLng / 2) ** 2;

        return Math.round(6371.0088 * 2 * Math.asin(Math.min(1, Math.sqrt(a))) * 100) / 100;
    }

    function distanceFee() {
        const km = distanceKm();

        if (km === null) {
            return null;
        }

        const tiers = [...delivery.tiers].sort((first, second) => Number(first.from) - Number(second.from));
        const tier = tiers.find((entry, index) => (km > Number(entry.from) || (index === 0 && km >= Number(entry.from))) && km <= Number(entry.to));

        return tier ? cents(tier.fee) : null;
    }

    function locate() {
        state.geoError = null;

        if (!navigator.geolocation) {
            state.geoError = 'Seu aparelho não permite compartilhar a localização.';
            render();

            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                state.geo = { lat: Math.round(position.coords.latitude * 1e6) / 1e6, lng: Math.round(position.coords.longitude * 1e6) / 1e6 };
                render();
            },
            () => {
                state.geoError = 'Não foi possível pegar sua localização. A taxa será combinada com a loja.';
                render();
            },
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 },
        );
    }

    function deliveryInfo() {
        const subtotal = subtotalCents();
        const minimum = cents(delivery.min_order);
        const freeOver = cents(delivery.free_over);
        const zone = delivery.zones.find((entry) => String(entry.id) === String(state.zoneId));
        let fee = zone ? cents(zone.fee) : distanceFee();
        let free = false;
        let missing = null;

        if (freeOver > 0) {
            if (subtotal >= freeOver) {
                fee = 0;
                free = true;
            } else {
                missing = freeOver - subtotal;
            }
        }

        return { fee, pending: fee === null, free, missing, belowMinimum: minimum > 0 && subtotal < minimum, minimum };
    }

    function totals() {
        const info = deliveryInfo();
        const subtotal = subtotalCents();
        const fee = state.fulfillment === 'entrega' ? info.fee : 0;

        const discount = state.promo ? Math.min(state.promo.discount, subtotal) : 0;

        return { info, subtotal, fee, discount, total: subtotal - discount + (fee ?? 0) };
    }

    function summary() {
        const { info, subtotal, fee, discount, total } = totals();
        const delivering = state.fulfillment === 'entrega';
        const feeLabel = !delivering ? '' : fee === null ? (hasZones && !state.zoneId ? 'pelo seu bairro' : 'a combinar') : fee === 0 && info.free ? 'grátis 🎉' : brl(fee);

        return `<dl class="order-summary">
            <div><dt>Subtotal</dt><dd>${brl(subtotal)}</dd></div>
            ${discount ? `<div class="is-discount"><dt>Cupom ${esc(state.promo.code)}</dt><dd>-${brl(discount)}</dd></div>` : ''}
            ${delivering ? `<div><dt>Entrega</dt><dd>${feeLabel}</dd></div>` : ''}
            <div class="is-total"><dt>Total estimado</dt><dd>${brl(total)}</dd></div>
        </dl>`;
    }

    // ---- Steps ----

    function render() {
        const steps = { 1: stepReceive, 2: stepData, 3: stepPay }[state.step];
        const alert = state.error ? `<p class="order-alert" role="alert">${esc(state.error)}</p>` : '';

        body.innerHTML = `
            <header class="order-head"><h2>Finalizar pedido</h2><p class="order-steps">Passo ${state.step} de 3</p></header>
            ${alert}${steps()}`;
    }

    function stepReceive() {
        const { info } = totals();
        const deliveryLocked = info.belowMinimum;

        return `
            <div class="order-choices" data-ck-fulfillment>
                <button type="button" class="order-card" data-ck-receive="retirada" aria-pressed="${state.fulfillment === 'retirada'}">
                    <b>Retirar na loja</b><small>Você busca no balcão</small>
                </button>
                <button type="button" class="order-card" data-ck-receive="entrega" aria-pressed="${state.fulfillment === 'entrega'}" ${deliveryLocked ? 'disabled' : ''}>
                    <b>Entrega</b><small>${deliveryLocked ? `Pedido mínimo ${brl(info.minimum)}` : hasZones ? 'Taxa pelo seu bairro' : 'Taxa combinada com a loja'}</small>
                </button>
            </div>
            ${deliveryLocked ? `<p class="order-hint">Para entrega o pedido mínimo é ${brl(info.minimum)}. Adicione mais itens ou retire na loja.</p>` : ''}
            ${info.missing !== null && !deliveryLocked ? `<p class="order-hint order-hint--good">Faltam ${brl(info.missing)} para entrega grátis.</p>` : ''}
            ${summary()}
            <footer class="order-footer">
                <button type="button" class="order-cta order-cta--ghost" data-ck-cart>Voltar ao pedido</button>
                <button type="button" class="order-cta" data-ck-next>Continuar</button>
            </footer>`;
    }

    function stepData() {
        const delivering = state.fulfillment === 'entrega';

        const zoneField = hasZones
            ? `<label class="order-field"><span>Bairro</span>
                    <select data-ck="zoneId">
                        <option value="">Escolha o seu bairro</option>
                        ${delivery.zones.map((zone) => `<option value="${zone.id}" ${String(zone.id) === String(state.zoneId) ? 'selected' : ''}>${esc(zone.name)} — ${brl(cents(zone.fee))}</option>`).join('')}
                        ${delivery.accepts_other ? `<option value="other" ${state.zoneId === 'other' ? 'selected' : ''}>Outro bairro (taxa a combinar)</option>` : ''}
                    </select></label>
               ${state.zoneId === 'other' ? field('neighborhood', 'Qual é o seu bairro?', state.neighborhood, 'Ex.: Jardim Novo') : ''}`
            : field('neighborhood', 'Bairro', state.neighborhood, 'Ex.: Centro');

        return `
            ${field('name', 'Seu nome', state.name, 'Ex.: Maria Silva', 'name')}
            ${field('phone', 'Seu WhatsApp', state.phone, '(69) 99999-9999', 'tel', 'tel')}
            <p class="order-hint">Para a loja confirmar seu pedido. Fica salvo neste aparelho.</p>
            ${
                delivering
                    ? `<h3 class="order-subtitle">Endereço de entrega</h3>
                        ${field('street', 'Rua / Avenida', state.street, 'Ex.: Rua das Flores', 'street-address')}
                        <div class="order-row">
                            ${field('number', 'Número', state.noNumber ? '' : state.number, '123', 'off', 'text', state.noNumber)}
                            <label class="order-check"><input type="checkbox" data-ck="noNumber" ${state.noNumber ? 'checked' : ''}> Sem número</label>
                        </div>
                        ${field('complement', 'Complemento (opcional)', state.complement, 'Casa 2, Apto 301')}
                        ${zoneField}
                        ${
                            hasDistance
                                ? `<div class="order-field"><button type="button" class="order-cta order-cta--ghost" data-ck-locate>${state.geo ? '📍 Localização compartilhada' : '📍 Usar minha localização para ver a taxa'}</button>
                                   ${state.geoError ? `<small>${esc(state.geoError)}</small>` : '<small>A localização é usada só para calcular a taxa e não fica salva.</small>'}</div>`
                                : ''
                        }
                        ${field('reference', 'Ponto de referência (opcional)', state.reference, 'Em frente à padaria, portão azul')}
                        <p class="order-hint">Seu endereço fica salvo neste aparelho para o próximo pedido.</p>`
                    : ''
            }
            <footer class="order-footer">
                <button type="button" class="order-cta order-cta--ghost" data-ck-back>Voltar</button>
                <button type="button" class="order-cta" data-ck-next>Continuar</button>
            </footer>`;
    }

    function stepPay() {
        const { total } = totals();

        return `
            <div class="order-choices order-choices--pay" data-ck-payments>
                ${PAYMENTS.map(([key, label, hint]) => `<button type="button" class="order-card" data-ck-pay="${key}" aria-pressed="${state.payment === key}"><b>${label}</b><small>${hint}</small></button>`).join('')}
            </div>
            ${state.payment === 'dinheiro' ? field('changeFor', 'Troco para quanto? (deixe vazio se não precisar)', state.changeFor, 'Ex.: 100,00', 'off', 'text') : ''}
            ${state.payment === 'pix' && !config.pix ? '<p class="order-hint">A loja confirma o Pix pelo WhatsApp.</p>' : ''}
            <div class="order-field order-coupon"><span>Cupom de desconto (opcional)</span>
                <div class="order-row"><input data-ck="coupon" type="text" value="${esc(state.coupon)}" placeholder="Ex.: BEMVINDO10" autocomplete="off" autocapitalize="characters" maxlength="30">
                <button type="button" class="order-cta order-cta--ghost" data-ck-coupon>${state.promo ? 'Trocar' : 'Aplicar'}</button></div></div>
            <label class="order-field"><span>Observação do pedido (opcional)</span>
                <textarea data-ck="notes" rows="2" maxlength="300" placeholder="Ex.: tocar a campainha">${esc(state.notes)}</textarea></label>
            ${summary()}
            <p class="order-privacy">Seus dados vão só para a loja. Endereço e observações são apagados em até 30 dias; nome e WhatsApp ficam com a loja para agilizar seus próximos pedidos. <a href="${esc(config.privacy_url ?? '/privacidade')}" target="_blank" rel="noopener">Política de Privacidade</a></p>
            ${config.off_hours ? '<p class="order-hint">A loja está fechada agora. Você pode enviar, e ela confirma quando abrir.</p>' : ''}
            <footer class="order-footer">
                <button type="button" class="order-cta order-cta--ghost" data-ck-back>Voltar</button>
                <button type="button" class="order-cta" data-ck-send ${state.sending ? 'disabled' : ''}>${state.sending ? 'Enviando…' : `Pedir pelo WhatsApp · ${brl(total)}`}</button>
            </footer>`;
    }

    function field(name, label, value, placeholder = '', autocomplete = 'off', type = 'text', disabled = false) {
        return `<label class="order-field"><span>${esc(label)}</span>
            <input data-ck="${name}" type="${type}" value="${esc(value)}" placeholder="${esc(placeholder)}" autocomplete="${autocomplete}" ${disabled ? 'disabled' : ''}></label>`;
    }

    // ---- Validation ----

    function validateData() {
        const digits = state.phone.replace(/\D/g, '');

        if (state.name.trim().length < 2) {
            return 'Informe seu nome.';
        }

        if (digits.length < 10 || digits.length > 13) {
            return 'Informe seu WhatsApp com DDD.';
        }

        if (state.fulfillment === 'entrega') {
            if (!state.street.trim()) {
                return 'Informe a rua.';
            }

            if (!state.noNumber && !state.number.trim()) {
                return 'Informe o número ou marque "Sem número".';
            }

            if (hasZones && !state.zoneId) {
                return 'Escolha o seu bairro.';
            }

            if ((!hasZones || state.zoneId === 'other') && !state.neighborhood.trim()) {
                return 'Informe o seu bairro.';
            }
        }

        return null;
    }

    function validatePay() {
        if (state.payment === 'dinheiro' && state.changeFor.trim() !== '') {
            const value = parseMoney(state.changeFor);

            if (value === null) {
                return 'Valor do troco inválido. Use o formato 100,00.';
            }

            if (value < totals().total) {
                return 'O troco precisa ser maior ou igual ao total do pedido.';
            }
        }

        return null;
    }

    // ---- Coupon (RF-17) ----

    async function applyCoupon() {
        const code = state.coupon.trim();

        state.promo = null;

        if (!code) {
            state.error = null;
            render();

            return;
        }

        try {
            const response = await fetch(config.coupon_url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ code, subtotal: subtotalCents() / 100 }) });
            const data = await response.json().catch(() => ({}));

            if (response.ok) {
                state.promo = { code: data.code, discount: cents(data.discount) };
                state.coupon = data.code;
                state.error = null;
            } else {
                state.error = data.message ?? 'Cupom inválido.';
            }
        } catch {
            state.error = 'Sem conexão. Verifique a internet e tente de novo.';
        }

        render();
    }

    // ---- Send ----

    async function send() {
        const problem = validatePay();

        if (problem) {
            state.error = problem;
            render();

            return;
        }

        remember();
        state.sending = true;
        state.error = null;
        render();

        const delivering = state.fulfillment === 'entrega';
        const zone = state.zoneId && state.zoneId !== 'other' ? Number(state.zoneId) : null;

        const payload = {
            items: store.items.map((item) => ({
                dish_id: Number(item.dishId),
                quantity: item.qty,
                notes: item.notes || null,
                variant_id: item.variantId,
                size_id: item.sizeId ?? null,
                flavor_ids: item.flavorIds ?? [],
                options: Object.entries(item.options).map(([id, quantity]) => ({ id: Number(id), quantity })),
            })),
            fulfillment: state.fulfillment,
            customer: { name: state.name.trim(), phone: state.phone },
            address: delivering
                ? { street: state.street.trim(), number: state.noNumber ? 's/n' : state.number.trim(), complement: state.complement.trim() || null, zone_id: zone, lat: state.geo?.lat ?? null, lng: state.geo?.lng ?? null, neighborhood: zone ? null : state.neighborhood.trim() || null, reference: state.reference.trim() || null }
                : null,
            payment_method: state.payment,
            change_for: state.payment === 'dinheiro' && state.changeFor.trim() ? state.changeFor.trim() : null,
            notes: state.notes.trim() || null,
            promo_code: state.promo?.code ?? (state.coupon.trim() || null),
            origin: sessionStorageGet('degusta-origin'),
            campaign: sessionStorageGet('degusta-campaign'),
        };

        let response;

        try {
            response = await fetch(config.orders_url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(payload) });
        } catch {
            fail('Sem conexão. Verifique a internet e tente de novo.');

            return;
        }

        const data = await response.json().catch(() => ({}));

        if (response.status === 201) {
            state.order = data;
            store.clear();
            ctx.refreshBar();
            state.sending = false;
            renderDone();

            return;
        }

        if (response.status === 422 && Array.isArray(data.items) && data.items.length) {
            const names = data.items.map((item) => item.name).filter(Boolean);
            store.removeDishes(data.items.map((item) => item.dish_id).filter(Boolean));
            ctx.showCart(`Alguns itens saíram do cardápio${names.length ? ` (${names.join(', ')})` : ''}. Revise o pedido.`);

            return;
        }

        if (response.status === 422 && data.issues?.length) {
            fail(data.issues[0].message);

            return;
        }

        if (response.status === 422 && data.errors) {
            fail(Object.values(data.errors).flat()[0] ?? 'Confira os dados do pedido.');

            return;
        }

        fail(response.status === 403 ? 'Esta loja não está recebendo pedidos agora.' : response.status === 429 ? 'Muitas tentativas. Aguarde um instante.' : 'Não foi possível enviar o pedido. Tente de novo.');
    }

    function fail(message) {
        state.sending = false;
        state.error = message;
        render();
    }

    // ---- Confirmation (RF-06a) ----

    function renderDone(confirmed = false, asked = false) {
        const order = state.order;

        body.innerHTML = `
            <header class="order-head"><h2>${confirmed ? 'Pedido enviado!' : 'Pedido criado!'}</h2><p class="order-code">Código <b>${esc(order.code)}</b></p></header>
            ${
                confirmed
                    ? `<p class="order-hint order-hint--good">A loja vai confirmar seu pedido pelo WhatsApp. Obrigado pela preferência!</p>`
                    : `<p class="order-hint">Falta só um passo: toque em enviar para a loja receber o pedido pelo WhatsApp.</p>
                       <a class="order-cta order-cta--wa" href="${esc(order.whatsapp_url)}" target="_blank" rel="noopener" data-ck-wa>Enviar pelo WhatsApp</a>`
            }
            ${
                order.pix_payload && !confirmed
                    ? `<div class="order-pix"><b>Pix copia e cola · ${brl(cents(order.total))}</b><code data-ck-pix>${esc(order.pix_payload)}</code><button type="button" class="order-cta order-cta--ghost" data-ck-copy>Copiar código Pix</button><small>Depois de pagar, mande o comprovante no WhatsApp.</small></div>`
                    : ''
            }
            ${
                asked && !confirmed
                    ? `<div class="order-confirm"><b>Conseguiu enviar seu pedido?</b><p>Código ${esc(order.code)}. Se o WhatsApp não abriu, toque em “Enviar pelo WhatsApp” de novo.</p>
                        <div class="order-footer"><button type="button" class="order-cta" data-ck-sent>Sim, enviei</button><button type="button" class="order-cta order-cta--ghost" data-ck-notsent>Ainda não</button></div></div>`
                    : ''
            }
            <footer class="order-footer">
                <a class="order-cta order-cta--ghost" href="${esc(order.tracking_url)}" target="_blank" rel="noopener">Acompanhar pedido</a>
                <button type="button" class="order-cta ${confirmed ? '' : 'order-cta--ghost'}" data-order-close>${confirmed ? 'Fazer outro pedido' : 'Fechar'}</button>
            </footer>`;
    }

    function whatsappEvent(event) {
        const url = config.whatsapp_url.replace('__TOKEN__', state.order.tracking_token);

        fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ event }), keepalive: true }).catch(() => {});
    }

    // ---- Persistence on the device (RF-06d) ----

    function remember() {
        try {
            window.localStorage.setItem(
                customerKey,
                JSON.stringify({ name: state.name, phone: state.phone, street: state.street, number: state.number, noNumber: state.noNumber, complement: state.complement, neighborhood: state.neighborhood, zoneId: state.zoneId, reference: state.reference, fulfillment: state.fulfillment, payment: state.payment }),
            );
        } catch {
            // Storage disabled: the next order simply starts empty.
        }
    }

    // ---- Wiring (property handlers replace the previous checkout's) ----

    body.onclick = (event) => {
        const target = event.target;
        const pressed = (selector) => target.closest(selector);

        if (pressed('[data-ck-receive]')) {
            state.fulfillment = pressed('[data-ck-receive]').dataset.ckReceive;
            render();
        } else if (pressed('[data-ck-pay]')) {
            state.payment = pressed('[data-ck-pay]').dataset.ckPay;
            render();
        } else if (pressed('[data-ck-locate]')) {
            locate();
        } else if (pressed('[data-ck-coupon]')) {
            applyCoupon();
        } else if (pressed('[data-ck-cart]')) {
            ctx.showCart();
        } else if (pressed('[data-ck-back]')) {
            state.error = null;
            state.step -= 1;
            render();
        } else if (pressed('[data-ck-next]')) {
            const issue = state.step === 2 ? validateData() : null;

            if (issue) {
                state.error = issue;
            } else {
                state.error = null;
                state.step += 1;
            }

            render();
        } else if (pressed('[data-ck-send]') && !state.sending) {
            send();
        } else if (pressed('[data-ck-wa]')) {
            whatsappEvent('opened');
            setTimeout(() => renderDone(false, true), 400);
        } else if (pressed('[data-ck-sent]')) {
            whatsappEvent('confirmed');
            renderDone(true);
        } else if (pressed('[data-ck-notsent]')) {
            whatsappEvent('not_sent');
            ctx.notify('Tudo bem. Toque em “Enviar pelo WhatsApp” quando puder.');
        } else if (pressed('[data-ck-copy]')) {
            navigator.clipboard?.writeText(state.order.pix_payload).then(() => ctx.notify('Código Pix copiado.'), () => ctx.notify('Não foi possível copiar. Selecione o código e copie.'));
        }
    };

    body.oninput = (event) => {
        const key = event.target.dataset?.ck;

        if (!key) {
            return;
        }

        state[key] = event.target.type === 'checkbox' ? event.target.checked : event.target.value;

        if (key === 'phone') {
            const masked = maskPhone(event.target.value);
            event.target.value = masked;
            state.phone = masked;
        }
    };

    body.onchange = (event) => {
        const key = event.target.dataset?.ck;

        if (key === 'zoneId' || key === 'noNumber') {
            state[key] = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
            render();
        }
    };

    render();
}

function readSaved(key) {
    try {
        return JSON.parse(window.localStorage.getItem(key)) ?? {};
    } catch {
        return {};
    }
}

function sessionStorageGet(key) {
    try {
        return window.sessionStorage.getItem(key);
    } catch {
        return null;
    }
}

function parseMoney(text) {
    const value = text.replace(/[^\d,.]/g, '');
    const normal = value.includes(',') ? value.replace(/\./g, '').replace(',', '.') : value;
    const number = Number(normal);

    return normal !== '' && Number.isFinite(number) ? Math.round(number * 100) : null;
}

function maskPhone(raw) {
    const digits = raw.replace(/\D/g, '').slice(0, 11);

    if (digits.length <= 2) {
        return digits;
    }

    if (digits.length <= 6) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }

    return digits.length <= 10 ? `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}` : `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}
