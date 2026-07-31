const CACHE_NAME = 'helpdesk-pwa-v1';
const STATIC_ASSETS = [
    './pwa/offline.html',
    './pwa/icons/icon-192x192.png',
    './pwa/icons/icon-512x512.png',
    './assets/css/style.css'
];

// Instalación: Cachear assets estáticos iniciales
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
        .then(cache => {
            console.log('[Service Worker] Cacheando archivos estáticos iniciales');
            return cache.addAll(STATIC_ASSETS);
        })
        .then(() => self.skipWaiting())
    );
});

// Activación: Limpiar cachés antiguos
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME) {
                        console.log('[Service Worker] Limpiando caché antigua:', cache);
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Intercepción de Fetch
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);

    // No cachear POST requests (formularios, acciones)
    if (request.method !== 'GET') {
        return;
    }

    // Estrategia para PHP y páginas dinámicas: Network-only con fallback offline
    if (request.headers.get('accept').includes('text/html') || url.pathname.endsWith('.php')) {
        event.respondWith(
            fetch(request)
            .catch(() => {
                console.log('[Service Worker] Modo Offline: Mostrando página offline');
                return caches.match('./pwa/offline.html');
            })
        );
        return;
    }

    // Estrategia para Estáticos (CSS, JS, Imágenes): Stale-while-revalidate
    if (url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2)$/)) {
        event.respondWith(
            caches.match(request).then(cachedResponse => {
                const fetchPromise = fetch(request).then(networkResponse => {
                    if (networkResponse && networkResponse.status === 200) {
                        caches.open(CACHE_NAME).then(cache => {
                            cache.put(request, networkResponse.clone());
                        });
                    }
                    return networkResponse;
                }).catch(() => {
                    console.log('[Service Worker] Fetch falló para recurso estático');
                });
                return cachedResponse || fetchPromise;
            })
        );
    }
});

// Preparación para notificaciones Push futuras
self.addEventListener('push', event => {
    const data = event.data ? event.data.json() : {};
    const title = data.title || 'Soporte Alto Alianza Notificación';
    const options = {
        body: data.body || 'Tienes una nueva actualización.',
        icon: './pwa/icons/icon-192x192.png',
        badge: './pwa/icons/icon-192x192.png'
    };
    event.waitUntil(self.registration.showNotification(title, options));
});
