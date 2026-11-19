'use strict';

var CACHE_NAME = 'registro-venta-v1';
var PRECACHE_URLS = [
    './style.css',
    './app.js',
    './manifest.webmanifest',
    './icons/icon-180.png',
    './icons/icon-192.png',
    './icons/icon-512.png'
];
var APP_SHELL_URL = './index.php';

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function (cache) {
                return cache.addAll(PRECACHE_URLS).then(function () {
                    return cache.add(APP_SHELL_URL);
                });
            })
            .then(function () {
                return self.skipWaiting();
            })
            .catch(function (error) {
                console.error('No se pudo preparar la caché offline:', error);
                throw error;
            })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (cacheNames) {
            return Promise.all(cacheNames.map(function (cacheName) {
                if (cacheName.indexOf('registro-venta-') === 0 && cacheName !== CACHE_NAME) {
                    return caches.delete(cacheName);
                }
                return Promise.resolve(false);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    var request = event.request;
    var requestUrl = new URL(request.url);

    if (request.method !== 'GET'
        || requestUrl.origin !== self.location.origin
        || requestUrl.href.indexOf(self.registration.scope) !== 0) {
        return;
    }

    event.respondWith(
        fetch(request).then(function (response) {
            if (response.ok && response.type === 'basic') {
                var responseToCache = response.clone();
                return caches.open(CACHE_NAME).then(function (cache) {
                    return cache.put(request, responseToCache);
                }).catch(function (error) {
                    console.error('No se pudo guardar la respuesta en caché:', error);
                }).then(function () {
                    return response;
                });
            }
            return response;
        }, function () {
            return caches.match(request).then(function (cachedResponse) {
                if (cachedResponse) {
                    return cachedResponse;
                }
                if (request.mode === 'navigate') {
                    return caches.match(APP_SHELL_URL).then(function (appShell) {
                        if (appShell) {
                            return appShell;
                        }
                    });
                }
                return Response.error();
            });
        })
    );
});
