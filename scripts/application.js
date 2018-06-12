// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const ContentManager = require('./content_manager');
const Convention = require('./convention');
const DateUtils = require('./date_utils');
const User = require('./user');

// Main object for the application. Controls all shared logic, the router and the views systems that
// together work to present the user interface.
class Application {
    constructor() {
        this.user_ = new User();
        this.content_ = new ContentManager();
        this.convention_ = new Convention();
        this.serviceWorkerRegistration_ = null;

        // Resolved when the user's information is available. When they have logged in to a
        // volunteer's account, it will also wait for the convention's information to have loaded.
        this.readyPromise_ = this.user_.ready.then(user => {
            if (user.isIdentified()) {
                return Promise.all([
                    this.content_.loadForUser(user),
                    this.convention_.loadForUser(user)

                ]).then(() => this);
            }

            return this;
        });

        // Register a Service Worker to provide offline support when this feature is available in
        // the browser. At time of writing, this is the case for Chrome, Opera and Firefox.
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .catch(error => console.warn(error));

            navigator.serviceWorker.ready.then(registration =>
                this.serviceWorkerRegistration_ = registration);
        }
    }

    // Gets the User object representing the local user.
    get user() { return this.user_; }

    // Gets the ContentManager object that manages arbitrary in-portal content.
    get content() { return this.content_; }

    // Gets the Convention object representing the convention driving this portal.
    get convention() { return this.convention_; }

    // Gets the Promise that is to be resolved with the Application instance when it's ready.
    get ready() { return this.readyPromise_; }

    // Gets the Service Worker Registration that's servicing this page load.
    get serviceWorkerRegistration() { return this.serviceWorkerRegistration_; }

    // Performs a hard refresh of the application. The dynamic caches will be thrown away, after
    // which the application will be reloaded to its root.
    hardRefresh() {
        let promises = [ Promise.resolve() ];

        // Drop all non-static caches entirely.
        if ('caches' in window) {
            promises.push(caches.keys().then(cacheNames => {
                var deletionQueue = [ Promise.resolve() ];
                cacheNames.forEach(cacheName => {
                    if (cacheName == 'static-2018-2')
                        return;

                    deletionQueue.push(caches.delete(cacheName));
                });

                return Promise.all(deletionQueue);
            }));
        }

        // Force-update the Service Worker on this command as well.
        if (this.serviceWorkerRegistration_)
            promises.push(this.serviceWorkerRegistration_.update());

        // Refresh the page after the hard refresh has completed.
        Promise.all(promises).then(() => window.location.href = '/');
    }
}

window.application = new Application();
