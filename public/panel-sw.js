/**
 * Panel service worker (RF-24): the minimum for the panel to be installable on a phone. It does
 * not cache pages or data — orders and the caixa must always be live — so every request goes to the network.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {
    // Network only: the request is left alone and the browser handles it as usual.
});
