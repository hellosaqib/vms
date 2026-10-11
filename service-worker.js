// Enhanced service-worker.js
const CACHE_NAME = 'britdrive-v3';
const OFFLINE_URL = '/';
const CACHE_ASSETS = [
    '/',
    '/manifest.json',
    '/assets/css/theme.min.css',
    '/assets/css/user.min.css',
    '/vendors/simplebar/simplebar.min.css',
    '/vendors/simplebar/simplebar.min.js',
    '/assets/js/config.js',
    '/assets/icons/icon-192x192.png',
    '/assets/icons/icon-512x512.png'
];


self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(CACHE_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== CACHE_NAME) {
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
        ])
    );
});

// Fetch - IMPORTANT: Never cache PHP files
self.addEventListener('fetch', event => {
    const url = event.request.url;
    
    // Skip non-GET requests
    if (event.request.method !== 'GET') return;
    
    // SKIP PHP files entirely - always fetch fresh
    if (url.includes('.php')) {
        event.respondWith(fetch(event.request));
        return;
    }
    
    // For static assets, use cache
    event.respondWith(
        caches.match(event.request)
            .then(cached => {
                return cached || fetch(event.request);
            })
    );
});
