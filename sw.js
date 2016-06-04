// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// Thanks Jake - https://jakearchibald.com/2014/offline-cookbook/#on-install-as-a-dependency
function promiseAny(promises) {
    return new Promise((resolve, reject) => {
        promises = promises.map(p => Promise.resolve(p));
        promises.forEach(p => p.then(r => { if (r) resolve(r); }));
        promises.reduce((a, b) => a.catch(() => b)).catch(() => reject(Error("All failed")));
    });
}

// -------------------------------------------------------------------------------------------------

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

    'images/material-icons.woff',
    'images/Roboto-Bold.woff',
    'images/Roboto-Regular.woff'
];

// Identifiers of the photos.
[
    1044545028, 1094954079, 113755755, 131172023, 1471428552, 1847797434, 1936960762, 205880836,
    2249626818, 243439886, 2615339157, 2621264915, 27925916, 2807102604, 2846741264, 289806870,
    2935908812, 3067994234, 3104018503, 3131408215, 3157557610, 316828961, 3177818173, 331067631,
    3467213544, 3674885751, 376436694, 3905577875, 3912778180, 4042808635, 4128177395, 4172578045,
    556637407, 786058562, 79098839, 838187230, 933957023, 995915586

].map(identifier => staticResources.push('images/photos/' + identifier + '.png'));

// -------------------------------------------------------------------------------------------------

self.addEventListener('install', event => {
    event.waitUntil(Promise.all([
        caches.open('static').then(cache => cache.addAll(staticResources)),
        skipWaiting()
    ]));
});

self.addEventListener('activate', event => {
    event.waitUntil(clients.claim());
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET')
        return fetch(event.request);

    var requestUrl = new URL(event.request.url);
    var responseUrl = null;

    if (requestUrl.pathname.endsWith('.woff'))
        responseUrl = event.request.url;

    if (requestUrl.pathname.startsWith('/events/') ||
        requestUrl.pathname.startsWith('/floors/') ||
        requestUrl.pathname.startsWith('/stewards/')) {
        responseUrl = '/';
    }

    var request = responseUrl || event.request;
    event.respondWith(
        // (1) Check if the request can be served from the static resource cache.
        caches.match(request, { cacheName: 'static' }).then(response => {
            if (response)
                return response;

            return promiseAny([
                // (2) Check if the request can be served from any of the other caches.
                caches.match(request),

                // (3) Fetch the request from the network, updating the dynamic cache on response.
                fetch(request).then(response => {
                    caches.open('dynamic').then(cache => cache.put(request, response));

                    return response.clone();
                })
            ]);
        }));
});
