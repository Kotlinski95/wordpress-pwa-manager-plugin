const CONFIG = __PWA_CONFIG__;
const CACHE_NAME = CONFIG.cacheName;

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.add(CONFIG.homeUrl).catch(() => {}))
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => Promise.all(
            cacheNames.map(cacheName => cacheName !== CACHE_NAME ? caches.delete(cacheName) : null)
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', event => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }
    if (!request.url.startsWith(self.location.origin)) {
        return;
    }
    if (/\/(wp-admin|wp-json|wp-includes)\//.test(request.url)) {
        return;
    }
    if (request.referrer && request.referrer.includes('/wp-admin/')) {
        return;
    }

    const isNavigation = request.mode === 'navigate'
        || request.destination === 'document'
        || (request.headers.get('accept') || '').includes('text/html');

    event.respondWith(isNavigation ? handleNavigationRequest(request) : handleAssetRequest(request));
});

async function handleNavigationRequest(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        if (CONFIG.offlineEnabled && CONFIG.offlineHtml) {
            return new Response(CONFIG.offlineHtml, {
                status: 200,
                headers: { 'Content-Type': 'text/html', 'Cache-Control': 'no-cache' },
            });
        }
        return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
    }
}

async function handleAssetRequest(request) {
    switch (CONFIG.cacheStrategy) {
        case 'network_first':
            return networkFirst(request);
        case 'stale_while_revalidate':
            return staleWhileRevalidate(request);
        case 'cache_first':
        default:
            return cacheFirst(request);
    }
}

async function cacheFirst(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }
    try {
        const networkResponse = await fetch(request);
        await addToCache(request, networkResponse);
        return networkResponse;
    } catch (error) {
        return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
    }
}

async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        await addToCache(request, networkResponse);
        return networkResponse;
    } catch (error) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
    }
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(CACHE_NAME);
    const cachedResponse = await cache.match(request);
    const fetchPromise = fetch(request).then(networkResponse => {
        addToCache(request, networkResponse.clone());
        return networkResponse;
    }).catch(() => cachedResponse);
    return cachedResponse || fetchPromise;
}

async function addToCache(request, response) {
    if (!response || response.status !== 200) {
        return;
    }
    const cache = await caches.open(CACHE_NAME);
    await cache.put(request, response.clone());
    await cleanupCache(cache);
}

async function cleanupCache(cache) {
    const requests = await cache.keys();
    if (requests.length > CONFIG.maxEntries) {
        const toDelete = requests.slice(0, requests.length - CONFIG.maxEntries);
        await Promise.all(toDelete.map(request => cache.delete(request)));
    }
}

self.addEventListener('sync', event => {
    if (event.tag === 'background-sync') {
        event.waitUntil(Promise.resolve());
    }
});

self.addEventListener('push', event => {
    if (!event.data) {
        return;
    }
    const options = {
        body: event.data.text(),
        icon: CONFIG.icon192,
        badge: CONFIG.icon192,
        vibrate: [100, 50, 100],
        data: { dateOfArrival: Date.now(), primaryKey: 1 },
    };
    event.waitUntil(self.registration.showNotification(CONFIG.appName, options));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(clients.openWindow(CONFIG.homeUrl));
});
