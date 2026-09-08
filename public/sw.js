/**
 * Courtly service worker.
 *
 * Deliberately a network pass-through with NO caching — the app keeps its own
 * queue-based offline handling in the page, and we don't want a stale shell.
 * The worker exists only to satisfy PWA installability requirements and give a
 * clean place to add caching later if needed.
 */
self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') {
        return;
    }
    var url;
    try {
        url = new URL(event.request.url);
    } catch (e) {
        return;
    }
    if (url.origin !== self.location.origin) {
        return;
    }
    event.respondWith(fetch(event.request));
});
