/**
 * Customer order tracking page (CT-03, RF-07): refreshes the headline and the timeline
 * by polling the status endpoint. Plain JS, no framework, like the cardápio. Polling
 * pauses while the tab is hidden and stops for good once the order has ended.
 */
const root = document.querySelector('[data-track]');

if (root) {
    const url = root.dataset.statusUrl;
    const headline = root.querySelector('[data-track-headline]');
    const steps = root.querySelector('[data-track-steps]');
    const INTERVAL = 8000;
    let timer = null;

    const render = (data) => {
        headline.textContent = data.headline;
        headline.dataset.tone = data.bad ? 'bad' : 'ok';

        if (steps) {
            steps.replaceChildren(
                ...data.steps.map((step) => {
                    const item = document.createElement('li');
                    item.className = 'track__step' + (step.done ? ' is-done' : '') + (step.current ? ' is-current' : '');

                    const dot = document.createElement('span');
                    dot.className = 'track__dot';
                    dot.setAttribute('aria-hidden', 'true');

                    const label = document.createElement('span');
                    label.className = 'track__step-label';
                    label.textContent = step.label;

                    const time = document.createElement('span');
                    time.className = 'track__step-time';
                    time.textContent = step.at ?? '';

                    item.append(dot, label, time);

                    return item;
                }),
            );
        }

        root.dataset.ended = data.ended ? 'true' : 'false';
    };

    const poll = async () => {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });

            if (response.status === 404) {
                stop();

                return;
            }

            if (response.ok) {
                const data = await response.json();
                render(data);

                if (data.ended) {
                    stop();
                }
            }
        } catch {
            // Offline for a moment: try again on the next tick.
        }
    };

    const start = () => {
        if (timer === null && root.dataset.ended !== 'true') {
            timer = setInterval(poll, INTERVAL);
        }
    };

    function stop() {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }

        root.querySelector('[data-track-refresh]')?.setAttribute('hidden', '');
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(timer);
            timer = null;
        } else {
            poll();
            start();
        }
    });

    start();
}
