// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// Path prefix for the photos, which will be lazily added to the static cache.
var photoPrefix = '/images/photos/';

// Name of the caches. And a list of caches that should be removed from storage.
var kStaticCache = 'static-2018-2';
var kDynamicCache = 'dynamic-2018-2';

// Static resources. These are presumed to never change.
var staticResources = [
    'images/icon-continents.jpg',
    'images/icon-mountains.jpg',
    'images/icon-oceans.jpg',
    'images/icon-rivers.jpg',
    'images/location.png',
    'images/login-background-mobile.jpg',
    'images/login-background.jpg',
    'images/login-logo.png',
    'images/logo-192.png',
    'images/menu-header.jpg?2',
    'images/no-photo.png',
    'images/notifications.png',

    'images/material-icons.woff',
    'images/Roboto-Bold.woff',
    'images/Roboto-Regular.woff'
];

// Blacklist of path names that will be ignored when attempting to focus an existing window in
// response to a click on a shown notification.
var focusBlacklist = [
    '/hallo',
    '/images',
    '/tools'
];

// The default notification that should be shown in case of an error.
var defaultNotification = {
    topic: 'An update is available',
    icon: '/images/logo-192.png',
    body: 'An update to the Anime 2018 schedule is now available!',
    url: '/'
};

// -------------------------------------------------------------------------------------------------

// Thanks Jake - https://jakearchibald.com/2014/offline-cookbook/#on-install-as-a-dependency
function promiseAny(promises) {
    return new Promise((resolve, reject) => {
        promises = promises.map(p => Promise.resolve(p));
        promises.forEach(p => p.then(r => { if (r) resolve(r); }));
        promises.reduce((a, b) => a.catch(() => b)).catch(() => reject(Error("All failed")));
    });
}

// Returns the name of the cache in which the |response| should be stored.
function getCacheForResponse(response) {
    if (response.url.includes(photoPrefix))
        return kStaticCache;

    return kDynamicCache;
}

// -------------------------------------------------------------------------------------------------

self.addEventListener('install', event => {
    var tasks = [];

    // Make sure that any deprecated caches have been removed.
    tasks.push(caches.keys().then(cacheNames => {
        var deletionQueue = [ Promise.resolve() ];
        cacheNames.forEach(cacheName => {
            if (cacheName == kStaticCache || cacheName == kDynamicCache)
                return;

            deletionQueue.push(caches.delete(cacheName));
        });

        return Promise.all(deletionQueue);
    }));

    // Make sure the latest static cache has been preloaded.
    tasks.push(
        caches.open(kStaticCache)
            .then(cache => cache.addAll(staticResources)));

    // Consider the Service Worker to have installed when all |tasks| have completed. At that point
    // don't wait for any still running Service Worker to complete.
    event.waitUntil(
        Promise.all(tasks).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
    event.waitUntil(clients.claim());
});

self.addEventListener('fetch', event => {
    var requestUrl = new URL(event.request.url);
    if (event.request.method !== 'GET' || requestUrl.pathname.startsWith('/tools/') ||
        requestUrl.pathname.startsWith('/hallo') || requestUrl.pathname.startsWith('/hello')) {
        event.respondWith(fetch(event.request));
        return;
    }

    var responseUrl = null;

    if (requestUrl.pathname.endsWith('.woff'))
        responseUrl = event.request.url;

    if (requestUrl.pathname.startsWith('/events/') ||
        requestUrl.pathname.startsWith('/floors/') ||
        requestUrl.pathname.startsWith('/volunteers/')) {
        responseUrl = '/';
    }

    var request = responseUrl || event.request;
    event.respondWith(
        // (1) Check if the request can be served from the static resource cache.
        caches.match(request, { cacheName: kStaticCache }).then(response => {
            if (response)
                return response;

            return promiseAny([
                // (2) Check if the request can be served from any of the other caches.
                caches.match(request),

                // (3) Fetch the request from the network, updating the dynamic cache on response.
                fetch(request).then(response => {
                    caches.open(getCacheForResponse(response)).then(cache =>
                        cache.put(request, response));

                    return response.clone();
                })
            ]);
        }));
});

self.addEventListener('push', event => {
    event.waitUntil(
        Promise.resolve(true)
            .then(() => event.data.json())
            .then(message => {
                if (typeof message !== 'object')
                    throw new Error('The JSON payload does not contain an object.');

                var payload = message.data;
                if (typeof payload !== 'object')
                    throw new Error('The JSON payload does not contain a `data` object.');

                for (const key of ['title', 'body', 'url']) {
                    if (!payload.hasOwnProperty(key))
                        throw new Error('Missing property: ' + key);
                }

                return payload;
            })
            .catch(error => {
                console.error('Cannot handle push event: ', error);
                return defaultNotification;
            })
            .then(message => {
                // Only the `data` property will be set by the Service Worker, all the other fields
                // included in the notification are controlled by the server.
                message.data = { url: message.url };

                return registration.showNotification(message.title, message);
            })
    );
});

self.addEventListener('notificationclick', event => {
    var notification = event.notification,
        url = notification.data.url;

    notification.close();

    // Focus the first client if any exists. Otherwise open a new window for the given URL.
    event.waitUntil(
        clients.matchAll({ type: 'window' }).then(clients => {
            for (var index = 0; index < clients.length; ++index) {
                var windowUrl = new URL(clients[index].url);
                var candidate = true;

                focusBlacklist.forEach(entry => {
                    if (windowUrl.pathname.startsWith(entry))
                        candidate = false;
                });

                if (!candidate)
                    continue;

                return Promise.all([
                    clients[index].navigate(url),
                    clients[index].focus()
                ]);
            }

            return self.clients.openWindow(url);
        })
    );
});
