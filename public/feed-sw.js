/**
 * Feed service worker (US-1.1): caches dish covers and the first video so a
 * returning customer gets an instant first paint.
 *
 * - Covers: cache-first.
 * - Videos: cache-first for the precached first video; range requests (how
 *   <video> fetches MP4) are answered from the cached full body as 206.
 */

const CACHE = 'feed-media-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type !== 'precache' || !Array.isArray(event.data.urls)) {
        return;
    }

    event.waitUntil(
        caches.open(CACHE).then((cache) =>
            Promise.all(
                event.data.urls.map(async (url) => {
                    if (await cache.match(url)) {
                        return;
                    }

                    try {
                        const response = await fetch(url, { mode: 'cors', credentials: 'omit' });

                        if (response.ok && response.status === 200) {
                            await cache.put(url, response);
                        }
                    } catch {
                        // Offline or CORS-blocked — skip, the network path still works.
                    }
                }),
            ),
        ),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    event.respondWith(
        caches.open(CACHE).then(async (cache) => {
            const cached = await cache.match(request.url);

            if (!cached) {
                return fetch(request);
            }

            const range = request.headers.get('range');

            return range ? rangeResponse(cached, range) : cached;
        }),
    );
});

async function rangeResponse(response, rangeHeader) {
    const body = await response.clone().arrayBuffer();
    const match = /bytes=(\d*)-(\d*)/.exec(rangeHeader);
    const size = body.byteLength;
    const start = match && match[1] ? Number(match[1]) : 0;
    const end = match && match[2] ? Math.min(Number(match[2]), size - 1) : size - 1;

    return new Response(body.slice(start, end + 1), {
        status: 206,
        statusText: 'Partial Content',
        headers: {
            'Content-Type': response.headers.get('Content-Type') || 'video/mp4',
            'Content-Range': `bytes ${start}-${end}/${size}`,
            'Content-Length': String(end - start + 1),
            'Accept-Ranges': 'bytes',
        },
    });
}
