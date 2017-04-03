// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// The content manager is responsible for mapping in arbitrary content that can be displayed in
// unison with a particular event or shift. It will load all known content when the portal is first
// launched, and persist it in memory for the lifetime of the application.
class ContentManager {
    constructor() {
        this.content_ = {};
    }

    // Returns the content associated with the |identifier|, if any, or NULL otherwise.
    get(identifier) {
        return this.content_[identifier] || null;
    }

    // Returns whether content is available for the |identifier|.
    has(identifier) {
        return this.content_.hasOwnProperty(identifier);
    }

    // Loads the content from the network (or the cache) for the given |user|. The |user| must have
    // identified with their account so that we can fetch the appropriate data.
    loadForUser(user) {
        if (!user.isIdentified())
            return Promise.reject(new Error('The user must be identified for loading the data.'));

        // TODO: The `content` should probably be made specific to the environment, i.e. contain
        // different information for stewards and gophers when we use the portal for both teams.
        const endpoint = '/content.json';

        return this.loadFromNetwork(endpoint).then(content => {
            this.content_ = content;
        });
    }

    // Loads the |requestUrl| from the network. Returns a Promise that will be resolved with the
    // contents once fetched, or reject with an Error in case of data errors.
    loadFromNetwork(requestUrl) {
        return new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();
            request.addEventListener('load', () => {
                try {
                    return resolve(JSON.parse(request.responseText));
                } catch (e) {
                    return reject(new Error('Server error: ' + e.message));
                }
            });

            request.addEventListener('error', () => resolve(null));

            request.open('GET', requestUrl, true);
            request.send();
        });
    }
}

module.exports = ContentManager;
