/**
 * Client feed (US-1.1–US-1.4) — vanilla JS, no framework.
 *
 * - The page opens on a grid of every dish (mockup); tapping one opens the
 *   vertical video feed on that dish, and "voltar" (or the phone's back
 *   button) returns to the grid.
 * - CSS scroll-snap pages between dishes; an IntersectionObserver finds the
 *   dish on screen, plays its video and pauses every other one.
 * - Only the next 1–2 videos are preloaded; far-away ones release their source.
 * - Videos start at 480p and use the 720p rendition when the connection allows.
 * - The category bar swaps the dish set and resets to that category's first dish.
 * - A bottom sheet shows the full detail; dismissed by swipe down / tap outside.
 */

const PRELOAD_AHEAD = 2;
const UNLOAD_DISTANCE = 3;
const SHEET_CLOSE_THRESHOLD = 140;
const HINT_KEY = 'feed-hint-seen';
const SESSION_KEY = 'feed-session';
const FLUSH_INTERVAL_MS = 15000;

const root = document.querySelector('[data-feed]');

if (root) {
    initFeed(root);
}

function initFeed(root) {
    const list = root.querySelector('[data-feed-list]');
    const template = document.getElementById('dish-template');
    const soundToggle = root.querySelector('[data-sound-toggle]');
    const categoryBar = root.querySelector('[data-category-bar]');

    const grid = root.querySelector('[data-grid]');

    const state = {
        muted: true,
        active: null,
        firstPlayed: false,
        renderedCategory: list.dataset.category ?? null,
    };

    // ---- Active dish detection ----

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (root.dataset.view === 'feed' && entry.isIntersecting && entry.intersectionRatio >= 0.6) {
                    setActive(entry.target);
                }
            });
        },
        { root: list, threshold: [0.6] },
    );

    function dishes() {
        return Array.from(list.querySelectorAll('[data-dish]'));
    }

    function observeAll() {
        observer.disconnect();
        dishes().forEach((dish) => observer.observe(dish));
    }

    function setActive(dish) {
        if (state.active === dish) {
            return;
        }

        state.active = dish;
        const all = dishes();
        const index = all.indexOf(dish);

        all.forEach((other, i) => {
            const video = other.querySelector('video');
            const distance = i - index;

            if (other === dish) {
                ensureSource(other, 'auto');
                video.muted = state.muted;
                play(video);
            } else {
                video.pause();

                if (distance > 0 && distance <= PRELOAD_AHEAD) {
                    ensureSource(other, 'auto');
                } else if (Math.abs(distance) > UNLOAD_DISTANCE) {
                    releaseSource(other);
                }
            }
        });

        root.dispatchEvent(new CustomEvent('feed:active-dish', { detail: { dish, dishId: dish.dataset.dishId } }));
    }

    function play(video) {
        const attempt = video.play();

        if (attempt && typeof attempt.catch === 'function') {
            attempt.then(() => (state.firstPlayed = true)).catch(() => {});
        }
    }

    // ---- Sources & quality (480p → 720p) ----

    function prefersHd() {
        const connection = navigator.connection;

        if (!connection || connection.saveData) {
            return false;
        }

        return connection.effectiveType === '4g' && (connection.downlink ?? 0) >= 5;
    }

    function sourceFor(dish) {
        const hd = dish.dataset.videoSrcHd;

        // The very first video always starts at 480p so it plays as fast as possible.
        return state.firstPlayed && hd && prefersHd() ? hd : dish.dataset.videoSrc;
    }

    function ensureSource(dish, preload) {
        const video = dish.querySelector('video');

        if (!video.getAttribute('src')) {
            const src = sourceFor(dish);

            if (!src) {
                return;
            }

            video.preload = preload;
            video.src = src;
        } else if (preload === 'auto') {
            video.preload = 'auto';
        }
    }

    function releaseSource(dish) {
        const video = dish.querySelector('video');

        if (video.getAttribute('src')) {
            video.pause();
            video.removeAttribute('src');
            video.load();
        }
    }

    // ---- Sound toggle & tap-to-pause ----

    soundToggle?.addEventListener('click', () => {
        state.muted = !state.muted;
        soundToggle.setAttribute('aria-pressed', String(!state.muted));
        soundToggle.setAttribute('aria-label', state.muted ? 'Ativar som' : 'Desativar som');

        const video = state.active?.querySelector('video');

        if (video) {
            video.muted = state.muted;
            play(video);
        }
    });

    list.addEventListener('click', (event) => {
        const video = event.target.closest('video');

        if (!video) {
            return;
        }

        video.paused ? play(video) : video.pause();
    });

    // ---- Category bar ----

    function markCategory(button) {
        categoryBar.querySelectorAll('[data-category]').forEach((other) => {
            other.setAttribute('aria-pressed', String(other === button));
        });

        const target = button.offsetLeft - (categoryBar.clientWidth - button.clientWidth) / 2;
        categoryBar.scrollTo({ left: Math.max(0, target), behavior: 'smooth' });
    }

    /**
     * Swap the feed to another category's dishes, via the category-switch endpoint.
     */
    async function loadCategory(button) {
        const url = button?.dataset.categoryUrl;

        if (!url) {
            return false;
        }

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            renderDishes(payload.dishes ?? []);
            state.renderedCategory = String(button.dataset.category);

            return true;
        } catch (error) {
            console.error('Falha ao trocar de categoria', error);

            return false;
        }
    }

    categoryBar?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-category]');

        if (!button || button.getAttribute('aria-pressed') === 'true') {
            return;
        }

        markCategory(button);

        if (await loadCategory(button)) {
            const first = dishes()[0];

            if (first) {
                setActive(first);
            }
        }
    });

    function renderDishes(items) {
        dishes().forEach(releaseSource);
        state.active = null;
        list.replaceChildren(...items.map(buildDish));
        list.scrollTo({ top: 0 });
        observeAll();
        precache(items);
    }

    function buildDish(data) {
        const fragment = template.content.cloneNode(true);
        const dish = fragment.querySelector('[data-dish]');
        const field = (name) => dish.querySelector(`[data-field="${name}"]`);

        dish.dataset.dishId = data.id;
        dish.dataset.videoSrc = data.video_url ?? '';
        dish.dataset.videoSrcHd = data.video_url_hd ?? '';
        dish.classList.toggle('dish--sold-out', Boolean(data.sold_out));

        if (data.cover_url) {
            field('video').poster = data.cover_url;
        }

        field('name').textContent = data.name ?? '';
        field('price').textContent = data.price ?? '';
        field('short_description').textContent = data.short_description ?? '';
        field('description').textContent = data.description ?? '';

        const badges = field('badges');
        badges.replaceChildren(
            ...(data.badges ?? []).map((label) => badgeElement(label)),
            ...(data.sold_out ? [badgeElement('Esgotado', 'badge--sold-out')] : []),
        );

        field('variants').replaceChildren(
            ...(data.variants ?? []).map((variant) => {
                const item = document.createElement('li');
                const name = document.createElement('span');
                const price = document.createElement('span');
                name.textContent = variant.name;
                price.textContent = variant.price;
                item.append(name, price);

                return item;
            }),
        );

        return dish;
    }

    function badgeElement(label, modifier = '') {
        const badge = document.createElement('span');
        badge.className = `badge ${modifier}`.trim();
        badge.textContent = label;

        return badge;
    }

    // ---- Detail sheet ----

    const sheet = root.querySelector('[data-sheet]');
    const backdrop = root.querySelector('[data-sheet-backdrop]');
    const sheetField = (name) => sheet.querySelector(`[data-sheet-field="${name}"]`);

    function openSheet() {
        const dish = state.active ?? dishes()[0];

        if (!dish) {
            return;
        }

        const field = (name) => dish.querySelector(`[data-field="${name}"]`);

        sheetField('name').textContent = field('name').textContent;
        sheetField('price').textContent = field('price').textContent;
        sheetField('badges').innerHTML = field('badges').innerHTML;
        sheetField('description').textContent = field('description').textContent || field('short_description').textContent;
        sheetField('variants').innerHTML = field('variants').innerHTML;

        sheet.style.transform = '';
        sheet.hidden = false;
        backdrop.hidden = false;
        sheet.scrollTop = 0;
    }

    function closeSheet() {
        sheet.hidden = true;
        backdrop.hidden = true;
        sheet.style.transform = '';
    }

    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-detail-open]')) {
            openSheet();
        }
    });

    backdrop.addEventListener('click', closeSheet);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !sheet.hidden) {
            closeSheet();
        }
    });

    let dragStart = null;
    let dragDelta = 0;

    sheet.addEventListener('pointerdown', (event) => {
        if (sheet.scrollTop > 0 && !event.target.closest('[data-sheet-handle]')) {
            return;
        }

        dragStart = event.clientY;
        dragDelta = 0;
        sheet.classList.add('sheet--dragging');
    });

    sheet.addEventListener('pointermove', (event) => {
        if (dragStart === null) {
            return;
        }

        dragDelta = Math.max(0, event.clientY - dragStart);
        sheet.style.transform = `translateY(${dragDelta}px)`;
    });

    const endDrag = () => {
        if (dragStart === null) {
            return;
        }

        sheet.classList.remove('sheet--dragging');
        dragStart = null;

        if (dragDelta > SHEET_CLOSE_THRESHOLD) {
            closeSheet();
        } else {
            sheet.style.transform = '';
        }
    };

    sheet.addEventListener('pointerup', endDrag);
    sheet.addEventListener('pointercancel', endDrag);
    sheet.addEventListener('pointerleave', endDrag);

    // ---- First-use hint ----

    const hint = root.querySelector('[data-feed-hint]');

    function maybeShowHint() {
        if (!hint || !hint.hidden || dishes().length < 2 || storageGet(HINT_KEY)) {
            return;
        }

        const dismissHint = () => {
            hint.hidden = true;
            storageSet(HINT_KEY, '1');
        };

        hint.hidden = false;
        // The programmatic scroll to the tapped dish must not dismiss it.
        setTimeout(() => {
            list.addEventListener('scroll', dismissHint, { once: true, passive: true });
            categoryBar?.addEventListener('click', dismissHint, { once: true });
        }, 300);
    }

    // ---- Grid ⇄ feed ----

    function showFeed() {
        root.dataset.view = 'feed';
    }

    function showGrid() {
        dishes().forEach((dish) => dish.querySelector('video').pause());
        state.active = null;
        root.dataset.view = 'grid';
    }

    async function openDish(dishId, categoryId) {
        const button = categoryBar?.querySelector(`[data-category="${categoryId}"]`);

        if (String(state.renderedCategory) !== String(categoryId)) {
            await loadCategory(button);
        }

        if (button) {
            markCategory(button);
        }

        showFeed();

        const target = list.querySelector(`[data-dish-id="${dishId}"]`) ?? dishes()[0];

        if (!target) {
            return;
        }

        list.scrollTop = target.offsetTop;
        state.active = null;
        setActive(target);
        maybeShowHint();

        if (!root.hasAttribute('data-preview')) {
            history.pushState({ feed: true }, '');
        }
    }

    grid?.addEventListener('click', (event) => {
        const item = event.target.closest('[data-open-dish]');

        if (item) {
            openDish(item.dataset.openDish, item.dataset.category);

            return;
        }

        const filter = event.target.closest('[data-grid-filter]');

        if (!filter) {
            return;
        }

        const category = filter.dataset.gridFilter;

        grid.querySelectorAll('[data-grid-filter]').forEach((other) => other.setAttribute('aria-pressed', String(other === filter)));
        grid.querySelectorAll('[data-grid-item]').forEach((cell) => {
            cell.hidden = category !== 'all' && cell.dataset.category !== category;
        });
    });

    root.querySelector('[data-back-to-grid]')?.addEventListener('click', () => {
        history.state?.feed ? history.back() : showGrid();
    });

    window.addEventListener('popstate', () => {
        if (root.dataset.view === 'feed') {
            showGrid();
        }
    });

    // ---- Share the dish on screen: native share sheet, else copy the link ----

    const toast = root.querySelector('[data-feed-toast]');
    let toastTimer = null;

    function showToast(message) {
        if (!toast) {
            return;
        }

        toast.textContent = message;
        toast.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (toast.hidden = true), 2200);
    }

    async function shareDish() {
        const dish = state.active ?? dishes()[0];
        const baseUrl = root.dataset.shareUrl;

        if (!dish || !baseUrl) {
            return;
        }

        const name = dish.querySelector('[data-field="name"]').textContent.trim();
        const restaurant = root.dataset.restaurantName ?? '';
        const url = `${baseUrl}?prato=${encodeURIComponent(dish.dataset.dishId)}`;
        const text = `Olha só: ${name}, no cardápio do ${restaurant}`;

        if (navigator.share) {
            try {
                await navigator.share({ title: `${name} · ${restaurant}`, text, url });

                return;
            } catch (error) {
                // Closing the share sheet is not an error; anything else falls back to copying.
                if (error?.name === 'AbortError') {
                    return;
                }
            }
        }

        try {
            await navigator.clipboard.writeText(url);
            showToast('Link copiado');
        } catch {
            window.open(`https://wa.me/?text=${encodeURIComponent(`${text} ${url}`)}`, '_blank', 'noopener');
        }
    }

    root.querySelector('[data-share]')?.addEventListener('click', shareDish);

    // ---- Service worker: cache covers + first video for repeat visits ----

    function precache(items) {
        if (!navigator.serviceWorker?.controller) {
            return;
        }

        const urls = items.map((item) => item.cover_url).filter(Boolean);

        if (items[0]?.video_url) {
            urls.push(items[0].video_url);
        }

        navigator.serviceWorker.controller.postMessage({ type: 'precache', urls });
    }

    if ('serviceWorker' in navigator && root.dataset.swUrl) {
        navigator.serviceWorker
            .register(root.dataset.swUrl, { scope: root.dataset.swScope })
            .then(() => navigator.serviceWorker.ready)
            .then(() => precache(currentItems()))
            .catch(() => {});
    }

    function currentItems() {
        return dishes().map((dish) => ({
            cover_url: dish.querySelector('video').getAttribute('poster'),
            video_url: dish.dataset.videoSrc,
        }));
    }

    // ---- Appearance live preview (US-6.1): the panel posts unsaved values ----

    if (root.hasAttribute('data-preview')) {
        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin || event.data?.type !== 'appearance') {
                return;
            }

            const { accent, font, logoUrl, coverUrl, description } = event.data;
            const style = document.documentElement.style;

            if (accent) {
                style.setProperty('--accent', accent);
            }

            if (font) {
                style.setProperty('--feed-display-font', `'${font}'`);
            }

            const logo = root.querySelector('[data-brand-logo]');
            const initial = root.querySelector('[data-brand-initial]');

            if (logo && logoUrl) {
                logo.src = logoUrl;
                logo.hidden = false;
                initial.hidden = true;
            }

            const cover = root.querySelector('[data-brand-cover]');

            if (cover && coverUrl) {
                cover.style.backgroundImage = `url('${coverUrl}')`;
            }

            const about = root.querySelector('[data-brand-description]');

            if (about && typeof description === 'string') {
                about.textContent = description;
                root.querySelector('[data-brand-about]').hidden = description.trim() === '';
                fitDescription(root);
            }
        });
    }

    fitDescription(root);
    document.fonts?.ready.then(() => fitDescription(root));

    // ---- Watch-time tracking (US-1.4 / US-6.3): batched, one report per dish ----

    initTracking(root);

    // ---- Boot: the grid is shown; nothing plays until a dish is tapped ----
    // A shared link (?prato=) opens the feed straight on that dish; its category is already rendered.

    observeAll();

    if (root.dataset.openDishOnLoad) {
        openDish(root.dataset.openDishOnLoad, list.dataset.category);
    }
}

function storageGet(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function storageSet(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Private mode / storage disabled — the hint simply shows again.
    }
}

/**
 * Accumulates the seconds each dish's video actually played and reports them
 * in batches — never one request per second. The server folds every report
 * into one row per (dish, session, day).
 */
function initTracking(root) {
    const url = root.dataset.trackUrl;

    if (!url) {
        return;
    }

    const sessionToken = sessionTokenFor();
    const pending = new Map();
    let current = null;
    let lastTime = null;

    const record = (dishId, seconds) => {
        if (!dishId) {
            return;
        }

        pending.set(dishId, (pending.get(dishId) ?? 0) + seconds);
    };

    root.addEventListener('feed:active-dish', (event) => {
        const video = event.detail.dish.querySelector('video');

        current = { dishId: event.detail.dishId, video };
        lastTime = null;
        record(current.dishId, 0);
    });

    // timeupdate deltas count only real playback; a loop restart yields a negative delta and is skipped.
    root.addEventListener(
        'timeupdate',
        (event) => {
            if (!current || event.target !== current.video) {
                return;
            }

            const time = event.target.currentTime;

            if (lastTime !== null) {
                const delta = time - lastTime;

                if (delta > 0 && delta < 2) {
                    record(current.dishId, delta);
                }
            }

            lastTime = time;
        },
        true,
    );

    const flush = () => {
        if (pending.size === 0) {
            return;
        }

        const views = Array.from(pending, ([dishId, seconds]) => ({ dish_id: Number(dishId), seconds: Math.round(seconds * 10) / 10 }));
        pending.clear();

        const body = JSON.stringify({ session_token: sessionToken, views });

        if (!(navigator.sendBeacon && navigator.sendBeacon(url, body))) {
            fetch(url, { method: 'POST', body, keepalive: true, headers: { 'Content-Type': 'application/json' } }).catch(() => {});
        }
    };

    setInterval(flush, FLUSH_INTERVAL_MS);
    document.addEventListener('visibilitychange', () => document.visibilityState === 'hidden' && flush());
    window.addEventListener('pagehide', flush);
}

function sessionTokenFor() {
    try {
        let token = window.sessionStorage.getItem(SESSION_KEY);

        if (!token) {
            token = crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2);
            window.sessionStorage.setItem(SESSION_KEY, token);
        }

        return token;
    } catch {
        return String(Date.now()) + Math.random().toString(16).slice(2);
    }
}

/**
 * Clamps the restaurant description to two lines, with "ver mais" only when
 * the text actually overflows.
 */
function fitDescription(root) {
    const about = root.querySelector('[data-brand-about]');
    const text = about?.querySelector('[data-brand-description]');
    const toggle = about?.querySelector('[data-description-toggle]');

    if (!about || !text || !toggle) {
        return;
    }

    about.classList.remove('is-expanded');
    toggle.textContent = 'ver mais';
    toggle.hidden = text.scrollHeight <= text.clientHeight + 1;

    toggle.onclick = () => {
        const expanded = about.classList.toggle('is-expanded');
        toggle.textContent = expanded ? 'ver menos' : 'ver mais';
    };
}
