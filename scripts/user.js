// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// Encapsulates information about the local user of the volunteer portal, and provides the ability
// to identify users based on the credentials they gave. Most of the functionality is asynchronous,
// so wait for the |ready| Promise to resolve before using an instance of this class.
class User {
    constructor() {
        this.name_ = null;
        this.token_ = null;

        this.options_ = {};

        this.readyPromise_ = this.load();
    }

    // Gets the Promise that is to be resolved after the initial load of the local user's data.
    get ready() { return this.readyPromise_; }

    // Returns whether the local user is a recognized volunteer for the current portal.
    isIdentified() { return this.name_ !== null; }

    // Gets the full, validated name of this user, or null when unavailable.
    get name() { return this.name_; }

    // Gets the security token of this user, or null when unavailable.
    get token() { return this.token_; }

    // Gets the value of |option|, or |defaultValue| when the option has not been previously set.
    getOption(option, defaultValue = null) {
        if (this.options_.hasOwnProperty(option))
            return this.options_[option];

        return defaultValue;
    }

    // Sets the value of |option| to |value|. Returns a Promise that will be resolved when the
    // updated information has been written to persistent storage.
    setOption(option, value) {
        this.options_[option] = value;
        return this.store();
    }

    // Will attempt to identify for a volunteer named |name|. Returns a Promise that will be
    // resolved with the logged in user when successful, or rejected with the error when it fails.
    identify(name) {
        const endpoint = '/anime/identify.php';

        return new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();
            request.addEventListener('load', () => {
                try {
                    const response = JSON.parse(request.responseText);
                    if (response.hasOwnProperty('error') && typeof response.error === 'string')
                        return reject(new Error(response.error));

                    if (response.hasOwnProperty('name') && typeof response.name === 'string' &&
                        response.hasOwnProperty('token') && typeof response.token === 'string') {
                        this.name_ = response.name;
                        this.token_ = response.token;

                        this.options_ = {};

                        return resolve(this.store());
                    }
                } catch (e) {
                    return reject(new Error('Server error: ' + e.message));
                }

                reject(new Error('Invalid response received from the server.'));
            });

            request.addEventListener('error', () =>
                reject(new Error('Unable to connect to the server.')));

            request.open('POST', endpoint, true);
            request.send(name);
        });
    }

    // Signs the local user out of their account by resetting the cached information. Returns a
    // Promise that will be resolved with the guest-containing User instance when completed.
    signOut() {
        this.name_ = null;
        this.token_ = null;

        this.options_ = {};

        return this.store();
    }

    // Loads information about the local user. Will remove stored information when deemed invalid.
    // Returns a Promise that will be resolved with the current instance when complete, because more
    // modern storage mechanisms rightfully require asynchronous operation.
    //
    // TODO: Switch to use IndexedDB for storage rather than localStorage, so that the information
    //       is also available within the Service Worker.
    load() {
        return new Promise(resolve => {
            const serializedInfo = localStorage['userInfo'];
            if (serializedInfo === undefined)
                return resolve(null);

            let userInfo = null;
            try {
                userInfo = JSON.parse(serializedInfo);
                if (userInfo.hasOwnProperty('name') && typeof userInfo.name === 'string' &&
                    userInfo.hasOwnProperty('token') && typeof userInfo.token === 'string' &&
                    userInfo.hasOwnProperty('options') && typeof userInfo.options === 'object') {
                    return resolve({ name: userInfo.name,
                                     token: userInfo.token,
                                     options: userInfo.options });
                }

            } catch (e) {}

            // The user's information could not be parsed or was invalid. 
            resolve(null);

        }).then(user => {
            if (!user)
                return this.signOut();

            this.name_ = user.name;
            this.token_ = user.token;
            this.options_ = user.options;

            return this;
        });
    }

    // Stores information about the local user in the cache. Will return a Promise that will be
    // resolved with the current instance when the operation is complete.
    store() {
        return new Promise(resolve => {
            if (!this.isIdentified()) {
                delete localStorage['userInfo'];
                return resolve(this);
            }

            localStorage['userInfo'] = JSON.stringify({
                name: this.name_,
                token: this.token_,
                options: this.options_
            });

            resolve();
        });
    }
}

module.exports = User;
