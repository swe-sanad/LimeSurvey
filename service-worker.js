/**
 * LimeSurvey PWA MVP service worker.
 *
 * Scope: installed at the LimeSurvey install root (same directory as index.php),
 * so its default scope covers the whole install regardless of whether LimeSurvey
 * runs at the domain root or in a subdirectory.
 *
 * Strategy:
 *  - Non-GET requests (survey submit/save, CSRF-bearing POSTs, any AJAX mutation)
 *    are NEVER intercepted: fetch() is not called and event.respondWith() is not
 *    invoked, so the browser handles them exactly as if this worker didn't exist.
 *  - HTML navigations use network-first, falling back to a cached offline page
 *    ONLY when the network is unreachable. Navigation responses are never cached,
 *    so a survey page (and the CSRF token embedded in it) can never be served stale.
 *  - Static theme/asset files (css/js/fonts/images under /assets/ or /themes/) use
 *    stale-while-revalidate: the cached copy is served immediately and refreshed in the
 *    background, so /assets/ hashed files stay fast and non-hashed /themes/ files still
 *    pick up changes on the next load (instead of staying stale until CACHE_VERSION bumps).
 *  - Everything else (API/admin/dynamic survey routes not covered above) passes
 *    through untouched.
 */

const CACHE_VERSION = 'ls-pwa-v1';
const STATIC_CACHE = CACHE_VERSION + '-static';
const OFFLINE_CACHE = CACHE_VERSION + '-offline';
const OFFLINE_URL = new URL('offline.html', self.registration.scope).href;

const STATIC_ASSET_RE = /\/(assets|themes)\/.+\.(css|js|mjs|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot)(\?.*)?$/i;

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(OFFLINE_CACHE).then(function (cache) {
            return cache.add(OFFLINE_URL);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys
                    .filter(function (key) { return key.indexOf(CACHE_VERSION) !== 0; })
                    .map(function (key) { return caches.delete(key); })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    const request = event.request;

    // Never touch non-GET requests: survey submit/save, CSRF-bearing POSTs, any mutation.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // HTML navigations: network-first, offline fallback only when the network fails.
    // Never cache the response itself (would risk serving a stale CSRF token/page).
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function () {
                return caches.match(OFFLINE_URL).then(function (cached) {
                    // If the offline page was evicted, fail cleanly rather than respondWith(undefined).
                    return cached || Response.error();
                });
            })
        );
        return;
    }

    // Static theme/asset files: stale-while-revalidate. Serve the cached copy fast, but always
    // refresh it in the background so non-content-hashed /themes/ files can't go stale forever.
    if (STATIC_ASSET_RE.test(url.pathname)) {
        event.respondWith(
            caches.open(STATIC_CACHE).then(function (cache) {
                return cache.match(request).then(function (cached) {
                    const network = fetch(request).then(function (response) {
                        if (response && response.ok) {
                            cache.put(request, response.clone());
                        }
                        return response;
                    }).catch(function () {
                        return cached;
                    });
                    return cached || network;
                });
            })
        );
        return;
    }

    // Everything else (API calls, dynamic survey routes, admin, exports, etc.):
    // pass through untouched.
});
