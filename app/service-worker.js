// app/service-worker.js
// Service worker Turnar PWA - cache base + notifiche push browser/iOS

const TURNAR_CACHE = 'turnar-pwa-v2';
const FALLBACK_URL = './index.php';

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(TURNAR_CACHE).then(function (cache) {
            return cache.addAll([
                './',
                './index.php',
                './login.php',
                './calendar.php',
                './communications.php',
                './manifest.php',
                './icon.php?size=180',
                './icon.php?size=192',
                './icon.php?size=512',
                '../assets/css/turnar.css'
            ]).catch(function () {
                return Promise.resolve();
            });
        })
    );

    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key !== TURNAR_CACHE) {
                    return caches.delete(key);
                }
                return Promise.resolve();
            }));
        })
    );

    self.clients.claim();
});

self.addEventListener('fetch', function (event) {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    event.respondWith(
        fetch(request)
            .then(function (response) {
                const clone = response.clone();
                caches.open(TURNAR_CACHE).then(function (cache) {
                    cache.put(request, clone).catch(function () {});
                });
                return response;
            })
            .catch(function () {
                return caches.match(request).then(function (cached) {
                    return cached || caches.match(FALLBACK_URL);
                });
            })
    );
});

function turnarSetBadge(count) {
    try {
        if (self.registration && 'setAppBadge' in self.registration) {
            const n = parseInt(count || 1, 10);
            return self.registration.setAppBadge(Number.isFinite(n) && n > 0 ? n : 1).catch(function () {});
        }
    } catch (e) {}
    return Promise.resolve();
}

function turnarClearBadge() {
    try {
        if (self.registration && 'clearAppBadge' in self.registration) {
            return self.registration.clearAppBadge().catch(function () {});
        }
    } catch (e) {}
    return Promise.resolve();
}

self.addEventListener('push', function (event) {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = {
            title: 'Turnar',
            body: event.data ? event.data.text() : 'Nuova notifica Turnar'
        };
    }

    const title = data.title || 'Turnar';
    const targetUrl = data.url || './index.php';
    const badgeCount = data.badge_count || data.count || 1;

    const options = {
        body: data.body || 'Hai una nuova notifica.',
        icon: data.icon || './icon.php?size=192',
        badge: data.badge || './icon.php?size=192',
        data: { url: targetUrl },
        tag: data.tag || 'turnar-notification',
        renotify: true
    };

    event.waitUntil(Promise.all([
        self.registration.showNotification(title, options),
        turnarSetBadge(badgeCount)
    ]));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const targetUrl = event.notification && event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : './index.php';

    event.waitUntil(
        Promise.all([
            turnarClearBadge(),
            self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
                for (const client of clientList) {
                    if ('focus' in client) {
                        client.navigate(targetUrl).catch(function () {});
                        return client.focus();
                    }
                }
                if (self.clients.openWindow) return self.clients.openWindow(targetUrl);
                return Promise.resolve();
            })
        ])
    );
});
