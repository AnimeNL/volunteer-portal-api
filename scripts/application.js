// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const Convention = require('./convention');
const DateUtils = require('./date_utils');
const User = require('./user');

// Main object for the application. Controls all shared logic, the router and the views systems that
// together work to present the user interface.
class Application {
    constructor() {
        this.user_ = new User();
        this.convention_ = new Convention(this.user_);

        // Resolved when the user's information is available. When they have logged in to a
        // volunteer's account, it will also wait for the convention's information to have loaded.
        this.readyPromise_ = this.user_.ready.then(user => {
            if (user.isIdentified())
                return this.convention_.loadForUser(user).then(convention => this);

            return this;
        });

        // Register a Service Worker to provide offline support when this feature is available in
        // the browser. At time of writing, this is the case for Chrome, Opera and Firefox.
        if ('serviceWorker' in navigator)
            navigator.serviceWorker.register('/sw.js', { scope: '/' });
    }

    // Gets the User object representing the local user.
    get user() { return this.user_; }

    // Gets the Convention object representing the convention driving this portal.
    get convention() { return this.convention_; }

    // Gets the Promise that is to be resolved with the Application instance when it's ready.
    get ready() { return this.readyPromise_; }

    // Performs a hard refresh of the application. The dynamic caches will be thrown away, after
    // which the application will be reloaded to its root.
    hardRefresh() {
        const cacheDeleter = window.caches ? window.caches.delete('dynamic')
                                           : Promise.resolve();

        cacheDeleter.then(() =>
            window.location.href = '/');
    }
}

window.application = new Application();
