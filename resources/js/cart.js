/**
 * Ordering on the client feed (RF-04, UI-02) — vanilla JS, no framework, loaded on demand.
 *
 * - The cart lives in localStorage, one per store, and survives reloads.
 * - A bottom sheet asks the choices of a product (variation, complement groups, note, quantity)
 *   with the same rules the server enforces: required groups block "Adicionar", limits and
 *   repeats are respected, the note stops at 140 characters.
 * - A fixed bar shows "Ver pedido · N itens · R$ total"; tapping it opens the cart.
 * - Prices here only show totals. The server prices the order again on checkout (RF-05).
 */

export const NOTES_LIMIT = 140;
const MAX_UNITS = 99;
const MAX_KG = 50;

export const esc = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

export const cents = (value) => Math.round(parseFloat(value ?? 0) * 100) || 0;

export const brl = (value) => 'R$ ' + (value / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const uid = () => Math.random().toString(36).slice(2, 10);

export function initCart(root) {
    const configElement = document.querySelector('[data-order-config]');
    const sheet = root.querySelector('[data-order-sheet]');

    if (!configElement || !sheet) {
        return;
    }

    const config = JSON.parse(configElement.textContent);

    rememberOrigin();
    const backdrop = root.querySelector('[data-order-backdrop]');
    const body = root.querySelector('[data-order-body]');
    const bar = root.querySelector('[data-cart-bar]');
    const toast = root.querySelector('[data-feed-toast]');
    const store = createStore(config.store_slug);
    const ctx = { config, store, root, body, esc, brl, cents, dishOf, lineCents, describe, subtotalCents, showCart, close, notify, refreshBar };

    let draft = null;
    let activeSheetDish = null;

    // ---- Catalog helpers ----

    function dishOf(id) {
        return config.dishes[id] ?? null;
    }

    /**
     * A pizza costs what its size and flavors say: the most expensive flavor, or the average
     * of them (rounded half up, in cents) when the store chose that rule (RF-14a).
     */
    function pizzaCents(item, dish) {
        const prices = (item.flavorIds ?? []).map((id) => cents(dish.flavors.find((flavor) => flavor.id === id)?.prices?.[item.sizeId]));

        if (prices.length === 0) {
            return cents(dish.price);
        }

        return config.pizza_rule === 'media' ? Math.floor((prices.reduce((sum, price) => sum + price, 0) * 2 + prices.length) / (prices.length * 2)) : Math.max(...prices);
    }

    function unitCents(item, dish) {
        const variant = dish.variants.find((entry) => entry.id === item.variantId);
        let total = dish.kind === 'pizza' ? pizzaCents(item, dish) : cents(variant ? variant.price : dish.price);

        for (const group of dish.groups) {
            for (const option of group.options) {
                total += cents(option.price) * (item.options[option.id] ?? 0);
            }
        }

        return total;
    }

    function lineCents(item) {
        const dish = dishOf(item.dishId);

        return dish ? Math.round(unitCents(item, dish) * item.qty) : 0;
    }

    function subtotalCents() {
        return store.items.reduce((sum, item) => sum + (isOrderable(item) ? lineCents(item) : 0), 0);
    }

    function isOrderable(item) {
        return Boolean(dishOf(item.dishId)?.orderable);
    }

    function describe(item) {
        const dish = dishOf(item.dishId);
        const lines = [];

        if (!dish) {
            return lines;
        }

        const variant = dish.variants.find((entry) => entry.id === item.variantId);

        if (variant) {
            lines.push(variant.name);
        }

        if (dish.kind === 'pizza') {
            const size = dish.sizes.find((entry) => entry.id === item.sizeId);
            const flavors = (item.flavorIds ?? []).map((id) => dish.flavors.find((flavor) => flavor.id === id)).filter(Boolean);
            const fraction = { 2: '½ ', 3: '⅓ ', 4: '¼ ' }[flavors.length] ?? '';

            if (size) {
                lines.push(`Tamanho: ${size.name}${size.slices ? ` (${size.slices} fatias)` : ''}`);
            }

            if (flavors.length) {
                lines.push(`${flavors.length === 1 ? 'Sabor' : 'Sabores'}: ` + flavors.map((flavor) => fraction + flavor.name).join(', '));
            }
        }

        for (const group of dish.groups) {
            const picked = group.options.filter((option) => item.options[option.id] > 0);

            if (picked.length) {
                lines.push(`${group.name}: ` + picked.map((option) => (item.options[option.id] > 1 ? `${item.options[option.id]}× ` : '') + option.name).join(', '));
            }
        }

        return lines;
    }

    // ---- Validation (mirrors the server rules) ----

    function groupCount(group, options) {
        return group.options.reduce((sum, option) => sum + (options[option.id] ?? 0), 0);
    }

    function problem(dish, selection) {
        if (dish.kind === 'pizza') {
            const size = dish.sizes.find((entry) => entry.id === selection.sizeId);

            if (!size) {
                return 'Escolha: Tamanho';
            }

            if ((selection.flavorIds ?? []).length === 0) {
                return 'Escolha: Sabor';
            }

            if (selection.flavorIds.length > size.max) {
                return `Tamanho ${size.name}: até ${size.max} ${size.max === 1 ? 'sabor' : 'sabores'}`;
            }
        } else if (dish.variants.length && !selection.variantId) {
            return 'Escolha uma opção';
        }

        for (const group of dish.groups) {
            const count = groupCount(group, selection.options);

            if (group.required && count === 0) {
                return `Escolha: ${group.name}`;
            }

            if (count > group.max) {
                return `${group.name}: no máximo ${group.max}`;
            }
        }

        if ((selection.notes ?? '').length > NOTES_LIMIT) {
            return `Observação: até ${NOTES_LIMIT} caracteres`;
        }

        return null;
    }

    // ---- Sheet frame ----

    function open() {
        document.querySelector('[data-sheet-backdrop]')?.click();
        sheet.hidden = false;
        backdrop.hidden = false;
        sheet.scrollTop = 0;
        bar.hidden = true;
    }

    function close() {
        sheet.hidden = true;
        backdrop.hidden = true;
        draft = null;
        refreshBar();
    }

    function notify(message) {
        if (!toast) {
            return;
        }

        toast.textContent = message;
        toast.hidden = false;
        clearTimeout(notify.timer);
        notify.timer = setTimeout(() => (toast.hidden = true), 2600);
    }

    function refreshBar() {
        const items = store.items.filter(isOrderable);
        const count = items.reduce((sum, item) => sum + (dishOf(item.dishId)?.sale_type === 'kg' ? 1 : item.qty), 0);

        bar.hidden = store.items.length === 0 || !sheet.hidden;
        bar.querySelector('[data-cart-count]').textContent = `${Math.round(count)} ${Math.round(count) === 1 ? 'item' : 'itens'}`;
        bar.querySelector('[data-cart-total]').textContent = brl(subtotalCents());
    }

    // ---- Choose a product ----

    function stepOf(dish) {
        return dish.sale_type === 'kg' ? 0.5 : 1;
    }

    function maxOf(dish) {
        return dish.sale_type === 'kg' ? MAX_KG : MAX_UNITS;
    }

    function startDraft(dishId, editing = null) {
        const dish = dishOf(dishId);

        if (!dish?.orderable) {
            notify('Este item não está disponível no momento.');

            return;
        }

        if (!config.accepting) {
            notify(`${config.closed_label}. A loja não está recebendo pedidos agora.`);

            return;
        }

        draft = editing
            ? { ...editing, options: { ...editing.options }, flavorIds: [...(editing.flavorIds ?? [])] }
            : { key: null, dishId, qty: stepOf(dish), notes: '', variantId: dish.variants.length === 1 ? dish.variants[0].id : null, options: {}, sizeId: null, flavorIds: [] };

        renderChoose();
        open();
    }

    function renderChoose() {
        const dish = dishOf(draft.dishId);

        const variants = dish.variants.length
            ? `<section class="order-group" data-variants>
                    <header><h3>Escolha uma opção</h3><span class="order-badge order-badge--required">Obrigatório</span></header>
                    ${dish.variants.map((variant) => `<button type="button" class="order-option" data-variant="${variant.id}"><span>${esc(variant.name)}</span><small>${brl(cents(variant.price))}</small></button>`).join('')}
               </section>`
            : '';

        const sizesSection = dish.kind === 'pizza'
            ? `<section class="order-group" data-sizes>
                    <header><h3>Tamanho</h3><span class="order-badge order-badge--required">Obrigatório</span></header>
                    ${dish.sizes
                        .map((size) => {
                            const lowest = Math.min(...dish.flavors.map((flavor) => cents(flavor.prices[size.id])).filter(Boolean));

                            return `<button type="button" class="order-option" data-size="${size.id}"><span>${esc(size.name)}<small>${size.slices ? `${size.slices} fatias · ` : ''}${size.max === 1 ? '1 sabor' : `até ${size.max} sabores`}</small></span><small>${Number.isFinite(lowest) ? `a partir de ${brl(lowest)}` : ''}</small></button>`;
                        })
                        .join('')}
               </section>
               <section class="order-group" data-flavors>
                    <header><div><h3>Sabores</h3><small data-flavor-rule>Escolha o tamanho primeiro</small></div><span class="order-badge order-badge--required">Obrigatório</span></header>
                    ${dish.flavors
                        .map((flavor) => `<button type="button" class="order-option" data-flavor="${flavor.id}" hidden><span>${esc(flavor.name)}${flavor.ingredients ? `<small>${esc(flavor.ingredients)}</small>` : ''}</span><small data-flavor-price></small></button>`)
                        .join('')}
               </section>`
            : '';

        const groups = dish.groups
            .map((group) => {
                const rule = group.mode === 'one' ? 'Escolha 1' : `Escolha até ${group.max}`;

                return `<section class="order-group" data-group="${group.id}">
                    <header>
                        <div><h3>${esc(group.name)}</h3><small>${rule}${group.mode === 'many' ? ` · <span data-group-count="${group.id}">0/${group.max}</span>` : ''}</small></div>
                        <span class="order-badge ${group.required ? 'order-badge--required' : ''}">${group.required ? 'Obrigatório' : 'Opcional'}</span>
                    </header>
                    ${group.options
                        .map((option) => {
                            const extra = option.price && cents(option.price) > 0 ? `<small>+ ${brl(cents(option.price))}</small>` : '';

                            return group.mode === 'many' && group.repeat
                                ? `<div class="order-option order-option--step" data-opt-row="${option.id}">
                                        <span>${esc(option.name)}${extra}</span>
                                        <span class="stepper"><button type="button" data-opt-step="-1" data-opt="${option.id}" data-group-id="${group.id}" aria-label="Menos ${esc(option.name)}">−</button><b data-opt-qty="${option.id}">0</b><button type="button" data-opt-step="1" data-opt="${option.id}" data-group-id="${group.id}" aria-label="Mais ${esc(option.name)}">+</button></span>
                                   </div>`
                                : `<button type="button" class="order-option" data-opt="${option.id}" data-group-id="${group.id}" aria-pressed="false"><span>${esc(option.name)}</span>${extra}</button>`;
                        })
                        .join('')}
                </section>`;
            })
            .join('');

        const unit = dish.sale_type === 'kg' ? ` <small>/ kg</small>` : '';

        body.innerHTML = `
            <header class="order-head"><h2>${esc(dish.name)}</h2><p>${dish.kind === 'pizza' ? 'a partir de ' : ''}${brl(cents(dish.price))}${unit}${dish.old_price ? ` <s>${brl(cents(dish.old_price))}</s>` : ''}</p></header>
            ${sizesSection}${variants}${groups}
            <label class="order-note"><span>Alguma observação?</span>
                <textarea data-note maxlength="${NOTES_LIMIT}" rows="2" placeholder="Ex.: sem cebola, bem passado, molho à parte"></textarea>
                <small><span data-note-count>0</span>/${NOTES_LIMIT}</small>
            </label>
            <footer class="order-footer">
                <span class="stepper stepper--big"><button type="button" data-qty="-1" aria-label="Diminuir">−</button><b data-qty-label></b><button type="button" data-qty="1" aria-label="Aumentar">+</button></span>
                <button type="button" class="order-cta" data-choose-confirm></button>
            </footer>`;

        body.querySelector('[data-note]').value = draft.notes;
        syncChoose();
    }

    function syncChoose() {
        const dish = dishOf(draft.dishId);

        body.querySelectorAll('[data-variant]').forEach((button) => button.setAttribute('aria-pressed', String(Number(button.dataset.variant) === draft.variantId)));

        if (dish.kind === 'pizza') {
            const size = dish.sizes.find((entry) => entry.id === draft.sizeId);

            body.querySelectorAll('[data-size]').forEach((button) => button.setAttribute('aria-pressed', String(Number(button.dataset.size) === draft.sizeId)));

            body.querySelectorAll('[data-flavor]').forEach((button) => {
                const flavor = dish.flavors.find((entry) => entry.id === Number(button.dataset.flavor));
                const price = size ? flavor.prices[size.id] : null;

                button.hidden = !size || price === undefined;
                button.setAttribute('aria-pressed', String(draft.flavorIds.includes(flavor.id)));
                button.querySelector('[data-flavor-price]').textContent = price !== undefined && size ? brl(cents(price)) : '';
            });

            const rule = body.querySelector('[data-flavor-rule]');

            if (rule) {
                rule.textContent = size
                    ? `Escolha até ${size.max} · ${draft.flavorIds.length}/${size.max}${size.max > 1 ? (config.pizza_rule === 'media' ? ' · o preço é a média dos sabores' : ' · cobra-se pelo sabor mais caro') : ''}`
                    : 'Escolha o tamanho primeiro';
            }
        }

        body.querySelectorAll('button[data-opt]:not([data-opt-step])').forEach((button) => button.setAttribute('aria-pressed', String((draft.options[button.dataset.opt] ?? 0) > 0)));
        body.querySelectorAll('[data-opt-qty]').forEach((label) => (label.textContent = String(draft.options[label.dataset.optQty] ?? 0)));

        dish.groups.forEach((group) => {
            const label = body.querySelector(`[data-group-count="${group.id}"]`);

            if (label) {
                label.textContent = `${groupCount(group, draft.options)}/${group.max}`;
            }
        });

        body.querySelector('[data-note-count]').textContent = String(draft.notes.length);
        body.querySelector('[data-qty-label]').textContent = dish.sale_type === 'kg' ? `${String(draft.qty).replace('.', ',')} kg` : `${draft.qty} un.`;

        const issue = problem(dish, draft);
        const total = brl(Math.round(unitCents(draft, dish) * draft.qty));
        const confirm = body.querySelector('[data-choose-confirm]');

        confirm.disabled = issue !== null;
        confirm.textContent = issue ?? `${draft.key ? 'Salvar' : 'Adicionar'} · ${total}`;
    }

    function chooseClick(target) {
        const dish = dishOf(draft.dishId);

        const variant = target.closest('[data-variant]');

        if (variant) {
            draft.variantId = Number(variant.dataset.variant);
        }

        const sizeButton = target.closest('[data-size]');

        if (sizeButton) {
            draft.sizeId = Number(sizeButton.dataset.size);
            const size = dish.sizes.find((entry) => entry.id === draft.sizeId);
            // Flavors that do not exist in the new size, or do not fit it, are dropped.
            draft.flavorIds = draft.flavorIds.filter((id) => dish.flavors.find((flavor) => flavor.id === id)?.prices[draft.sizeId] !== undefined).slice(0, size.max);
        }

        const flavorButton = target.closest('[data-flavor]');

        if (flavorButton) {
            const id = Number(flavorButton.dataset.flavor);
            const size = dish.sizes.find((entry) => entry.id === draft.sizeId);

            if (draft.flavorIds.includes(id)) {
                draft.flavorIds = draft.flavorIds.filter((entry) => entry !== id);
            } else if (size && draft.flavorIds.length < size.max) {
                draft.flavorIds = [...draft.flavorIds, id];
            } else if (size && size.max === 1) {
                draft.flavorIds = [id];
            }
        }

        const step = target.closest('[data-opt-step]');

        if (step) {
            const group = dish.groups.find((entry) => entry.id === Number(step.dataset.groupId));
            const id = step.dataset.opt;
            const next = (draft.options[id] ?? 0) + Number(step.dataset.optStep);

            if (next >= 0 && !(next > (draft.options[id] ?? 0) && groupCount(group, draft.options) >= group.max)) {
                draft.options[id] = next;
            }
        } else {
            const option = target.closest('button[data-opt]');

            if (option) {
                const group = dish.groups.find((entry) => entry.id === Number(option.dataset.groupId));
                const id = option.dataset.opt;
                const selected = (draft.options[id] ?? 0) > 0;

                if (group.mode === 'one') {
                    group.options.forEach((entry) => delete draft.options[entry.id]);

                    if (!selected || group.required) {
                        draft.options[id] = 1;
                    }
                } else if (selected) {
                    delete draft.options[id];
                } else if (groupCount(group, draft.options) < group.max) {
                    draft.options[id] = 1;
                }
            }
        }

        const qty = target.closest('[data-qty]');

        if (qty) {
            const next = Math.round((draft.qty + Number(qty.dataset.qty) * stepOf(dish)) * 10) / 10;
            draft.qty = Math.min(Math.max(next, stepOf(dish)), maxOf(dish));
        }

        if (target.closest('[data-choose-confirm]') && problem(dish, draft) === null) {
            const saved = store.upsert(draft);
            track('add', Number(draft.dishId));
            close();
            notify(saved.merged ? 'Quantidade somada no pedido.' : 'Adicionado ao pedido.');

            return;
        }

        syncChoose();
    }

    // ---- Cart ----

    function showCart(message = null) {
        draft = null;
        const items = store.items;

        if (items.length === 0) {
            body.innerHTML = `<header class="order-head"><h2>Seu pedido</h2></header>
                <p class="order-empty">Seu pedido está vazio. Escolha os produtos e as quantidades.</p>
                <footer class="order-footer"><button type="button" class="order-cta order-cta--ghost" data-order-close>Voltar ao cardápio</button></footer>`;
            open();

            return;
        }

        const blocked = items.some((item) => !isOrderable(item));

        body.innerHTML = `
            <header class="order-head"><h2>Seu pedido</h2></header>
            ${message ? `<p class="order-alert" role="alert">${esc(message)}</p>` : ''}
            <ul class="order-lines" data-cart-lines>
                ${items
                    .map((item) => {
                        const dish = dishOf(item.dishId);
                        const gone = !isOrderable(item);
                        const details = describe(item).map((line) => `<small>${esc(line)}</small>`).join('');
                        const note = item.notes ? `<small>Obs.: ${esc(item.notes)}</small>` : '';
                        const qty = dish?.sale_type === 'kg' ? `${String(item.qty).replace('.', ',')} kg` : `${item.qty} un.`;

                        return `<li class="order-line${gone ? ' is-gone' : ''}" data-line="${item.key}">
                            <div class="order-line__main"><strong>${esc(dish?.name ?? 'Item indisponível')}</strong>${gone ? '<small class="order-line__gone">Indisponível no momento</small>' : details + note}</div>
                            <div class="order-line__side">
                                <span>${gone ? '' : brl(lineCents(item))}</span>
                                ${gone ? '' : `<span class="stepper"><button type="button" data-line-step="-1" aria-label="Diminuir">−</button><b>${qty}</b><button type="button" data-line-step="1" aria-label="Aumentar">+</button></span>`}
                            </div>
                            <div class="order-line__actions">${gone ? '' : '<button type="button" data-line-edit>Editar</button>'}<button type="button" data-line-remove>Remover</button></div>
                        </li>`;
                    })
                    .join('')}
            </ul>
            <p class="order-total"><span>Subtotal</span><b>${brl(subtotalCents())}</b></p>
            ${blocked ? '<p class="order-alert" role="alert">Tire os itens indisponíveis para continuar.</p>' : ''}
            <footer class="order-footer">
                <button type="button" class="order-cta order-cta--ghost" data-order-close>Adicionar mais</button>
                <button type="button" class="order-cta" data-go-checkout ${blocked ? 'disabled' : ''}>Continuar</button>
            </footer>`;

        open();
    }

    async function goCheckout() {
        const module = await import('./checkout.js');
        track('checkout');
        module.startCheckout(ctx);
    }

    function cartClick(target) {
        const line = target.closest('[data-line]');

        if (line) {
            const item = store.items.find((entry) => entry.key === line.dataset.line);
            const dish = item ? dishOf(item.dishId) : null;

            if (target.closest('[data-line-remove]')) {
                store.remove(item.key);
                showCart();
            } else if (target.closest('[data-line-edit]')) {
                startDraft(item.dishId, item);
            } else if (target.closest('[data-line-step]') && dish) {
                const next = Math.round((item.qty + Number(target.closest('[data-line-step]').dataset.lineStep) * stepOf(dish)) * 10) / 10;

                if (next <= 0) {
                    store.remove(item.key);
                } else {
                    store.setQty(item.key, Math.min(next, maxOf(dish)));
                }

                showCart();
            }

            return;
        }

        if (target.closest('[data-go-checkout]')) {
            goCheckout();
        }
    }

    // ---- Wiring ----

    root.addEventListener('click', (event) => {
        const target = event.target;

        const add = target.closest('[data-add-to-cart]');

        if (add) {
            startDraft(add.closest('[data-dish]')?.dataset.dishId);

            return;
        }

        if (target.closest('[data-sheet-add]')) {
            startDraft(activeSheetDish);

            return;
        }

        if (target.closest('[data-cart-bar]')) {
            showCart();

            return;
        }

        if (target.closest('[data-order-close]')) {
            close();

            return;
        }

        if (!sheet.hidden && sheet.contains(target)) {
            if (draft) {
                chooseClick(target);
            } else {
                cartClick(target);
            }
        }
    });

    root.addEventListener('input', (event) => {
        if (draft && event.target.matches('[data-note]')) {
            draft.notes = event.target.value.slice(0, NOTES_LIMIT);
            syncChoose();
        }
    });

    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', (event) => event.key === 'Escape' && !sheet.hidden && close());

    root.addEventListener('feed:sheet-open', (event) => {
        activeSheetDish = event.detail.dishId;
        const dish = dishOf(activeSheetDish);
        const button = root.querySelector('[data-sheet-add]');

        if (button) {
            button.hidden = !dish?.orderable;
        }
    });

    // ---- Funnel (RF-21): fire-and-forget, never blocks the customer ----

    function track(event, dishId = null) {
        try {
            const token = window.sessionStorage.getItem('feed-session') ?? '';

            if (token.length >= 8 && config.events_url) {
                navigator.sendBeacon?.(config.events_url, JSON.stringify({ session_token: token, event, dish_id: dishId }));
            }
        } catch {
            // Tracking is optional.
        }
    }

    track('visit');
    refreshBar();
    root.dataset.ordering = 'ready';
}

/**
 * "?origem=instagram&campanha=sextou" on the link (or the QR code) is kept for the whole visit,
 * so the order records where it came from (RF-20).
 */
function rememberOrigin() {
    try {
        const params = new URLSearchParams(window.location.search);

        for (const [param, key] of [['origem', 'degusta-origin'], ['campanha', 'degusta-campaign'], ['cupom', 'degusta-coupon']]) {
            if (params.get(param)) {
                window.sessionStorage.setItem(key, params.get(param).slice(0, 60));
            }
        }
    } catch {
        // Storage disabled: the order is recorded as "direto".
    }
}

/**
 * The cart of one store, persisted in localStorage. Lines with the same product, choices
 * and note are merged by adding their quantity.
 */
function createStore(slug) {
    const storageKey = `degusta-cart:${slug}`;
    let items = [];

    try {
        const saved = JSON.parse(window.localStorage.getItem(storageKey));

        if (saved?.v === 1 && Array.isArray(saved.items)) {
            items = saved.items;
        }
    } catch {
        // Storage unavailable or corrupted: start with an empty cart.
    }

    const persist = () => {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify({ v: 1, items }));
        } catch {
            // Private mode: the cart simply lasts until the page closes.
        }
    };

    const signature = (item) => JSON.stringify([item.dishId, item.variantId, item.sizeId ?? null, [...(item.flavorIds ?? [])].sort(), Object.entries(item.options).filter(([, qty]) => qty > 0).sort(), item.notes.trim()]);

    return {
        get items() {
            return items;
        },
        upsert(draft) {
            const clean = { ...draft, options: Object.fromEntries(Object.entries(draft.options).filter(([, qty]) => qty > 0)), notes: draft.notes.trim() };

            if (clean.key) {
                items = items.map((item) => (item.key === clean.key ? clean : item));
                persist();

                return { merged: false };
            }

            const twin = items.find((item) => signature(item) === signature(clean));

            if (twin) {
                twin.qty = Math.round((twin.qty + clean.qty) * 10) / 10;
            } else {
                items.push({ ...clean, key: uid() });
            }

            persist();

            return { merged: Boolean(twin) };
        },
        setQty(key, qty) {
            items = items.map((item) => (item.key === key ? { ...item, qty } : item));
            persist();
        },
        remove(key) {
            items = items.filter((item) => item.key !== key);
            persist();
        },
        removeDishes(dishIds) {
            const gone = dishIds.map(String);
            items = items.filter((item) => !gone.includes(String(item.dishId)));
            persist();
        },
        clear() {
            items = [];
            persist();
        },
    };
}
