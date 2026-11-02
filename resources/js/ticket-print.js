// Kitchen ticket (RF-23): the paper width and font size are choices of this device, kept in its
// own localStorage — nothing about the printer is saved on the server.
const KEY = 'degusta-ticket';
const defaults = { width: '80', font: '13', auto: false };

function load() {
    try {
        return { ...defaults, ...(JSON.parse(window.localStorage.getItem(KEY)) ?? {}) };
    } catch {
        return { ...defaults };
    }
}

function save(settings) {
    try {
        window.localStorage.setItem(KEY, JSON.stringify(settings));
    } catch {
        // Storage disabled: the choice lasts until the page closes.
    }
}

const root = document.querySelector('[data-ticket]');

if (root) {
    const settings = load();
    const apply = () => {
        root.style.setProperty('--ticket-width', `${settings.width}mm`);
        root.style.setProperty('--ticket-font', `${settings.font}px`);

        let page = document.getElementById('ticket-page-size');

        if (!page) {
            page = document.createElement('style');
            page.id = 'ticket-page-size';
            document.head.appendChild(page);
        }

        page.textContent = `@page { size: ${settings.width}mm auto; margin: 0; }`;
    };

    for (const input of document.querySelectorAll('[data-ticket-setting]')) {
        const name = input.dataset.ticketSetting;

        if (input.type === 'checkbox') {
            input.checked = Boolean(settings[name]);
        } else {
            input.value = settings[name];
        }

        input.addEventListener('change', () => {
            settings[name] = input.type === 'checkbox' ? input.checked : input.value;
            save(settings);
            apply();
        });
    }

    document.querySelector('[data-ticket-print]')?.addEventListener('click', () => window.print());
    apply();

    if (settings.auto && root.dataset.ticket === 'order') {
        setTimeout(() => window.print(), 300);
    }
}
