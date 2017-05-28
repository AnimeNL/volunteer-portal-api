// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// This class encapsulates the functionality to register for Push Notifications with the Push
// Service chosen by the browser, then the Firebase Federation Service, then the Volunteer Portal
// back-end system for finalizing the subscription.
//
// Push Notifications are only available for browsers that support both the W3C Push API and the
// WHATWG Notifications API, and have chosen a Push Service that's supported by Firebase.
class UserNotifications {
    // Returns whether the client-side aspects of Push Notifications are supported by the browser.
    isSupported() {
        return navigator.serviceWorker &&
               window.hasOwnProperty('Notification') &&
               window.hasOwnProperty('PushManager');
    }

    // Returns whether the browser is currently subscribed for Push Notifications. We don't verify
    // whether the subscription is included in the user's topic.
    isSubscribed() {
        if (!this.isSupported())
            return Promise.resolve(false /* subscribed */);

        return navigator.serviceWorker.ready.then(registration => {
            return registration.pushManager.getSubscription();
        }).then(subscription => {
            return !!subscription;
        });
    }

    // Creates a new subscription for the user identified by |token|.
    subscribe(token) {
        // TODO: Actually create the subscription.

        return this.isSubscribed();
    }

    // Removes the active subscription, if any. We rely on the Firebase Federation Service to pick
    // up on error codes whilst sending the message for server-side unsubscription.
    unsubscribe() {
        if (!this.isSupported())
            return Promise.resolve(false /* subscribed */);

        return navigator.serviceWorker.ready.then(registration => {
            return registration.pushManager.getSubscription();
        }).then(subscription => {
            if (!subscription)
                return true /* success */;

            return subscription.unsubscribe();
        }).then(success => {
            if (!success)
                console.error('Unable to unsubscribe the push subscription.');

            return this.isSubscribed();
        });
    }
}

module.exports = UserNotifications;
