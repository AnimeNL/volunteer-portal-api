// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// ID of the Firebase project used for the Volunteer Portal.
const PORTAL_SENDER_ID = '820417111101';

// This class encapsulates the functionality to register for Push Notifications with the Push
// Service chosen by the browser, then the Firebase Federation Service, then the Volunteer Portal
// back-end system for finalizing the subscription.
//
// Push Notifications are only available for browsers that support both the W3C Push API and the
// WHATWG Notifications API, and have chosen a Push Service that's supported by Firebase.
class UserNotifications {
    // Returns whether the client-side aspects of Push Notifications are supported by the browser.
    //
    // These checks were copied from the following file:
    // https://github.com/firebase/firebase-js-sdk/blob/master/src/messaging/controllers/window-controller.ts
    isSupported() {
        return 'serviceWorker' in navigator &&
               'PushManager' in window &&
               'Notification' in window &&
                ServiceWorkerRegistration.prototype.hasOwnProperty('showNotification') &&
                PushSubscription.prototype.hasOwnProperty('getKey');;
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

    // Creates a new subscription for the user identified by |userToken|.
    subscribe(userToken) {
        if (!this.isSupported())
            return Promise.resolve(false /* subscribed */);
        
        return new Promise(resolve => {
            Notification.requestPermission(permission => {
                if (permission !== 'granted')
                    return resolve();

                resolve(navigator.serviceWorker.ready.then(registration => {
                    // This is the VAPID key owned by the Firebase Federation Service.
                    const FCM_APPLICATION_SERVER_KEY = [
                        0x04, 0x33, 0x94, 0xF7, 0xDF, 0xA1, 0xEB, 0xB1, 0xDC, 0x03, 0xA2, 0x5E,
                        0x15, 0x71, 0xDB, 0x48, 0xD3, 0x2E, 0xED, 0xED, 0xB2, 0x34, 0xDB, 0xB7,
                        0x47, 0x3A, 0x0C, 0x8F, 0xC4, 0xCC, 0xE1, 0x6F, 0x3C, 0x8C, 0x84, 0xDF,
                        0xAB, 0xB6, 0x66, 0x3E, 0xF2, 0x0C, 0xD4, 0x8B, 0xFE, 0xE3, 0xF9, 0x76,
                        0x2F, 0x14, 0x1C, 0x63, 0x08, 0x6A, 0x6F, 0x2D, 0xB1, 0x1A, 0x95, 0xB0,
                        0xCE, 0x37, 0xC0, 0x9C, 0x6E ];

                    // Create a Push Subscription with the Push Service chosen by the browser.
                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: new Uint8Array(FCM_APPLICATION_SERVER_KEY)
                    });

                }).then(subscription => {
                    const subscriptionData = JSON.parse(JSON.stringify(subscription));
                    const headers = new Headers();
                    const payload =
                        'authorized_entity=' + PORTAL_SENDER_ID + '&' +
                        'endpoint=' + subscriptionData.endpoint + '&' +
                        'encryption_key=' + subscriptionData.keys.p256dh + '&' +
                        'encryption_auth=' + subscriptionData.keys.auth;

                    headers.append('Content-Type', 'application/x-www-form-urlencoded');

                    // Associate the created |subscription| with the |PORTAL_SENDER_ID| through the
                    // Firebase Federation Service. Or whatever it's formal name is.
                    return fetch('https://fcm.googleapis.com/fcm/connect/subscribe', {
                        method: 'POST',
                        headers: headers,
                        body: payload
                    });
                }).then(response => {
                    return response.json();
                }).then(response => {
                    if (response.error) {
                        console.error('FCM Subscription failed: ' + response.error.message);
                        return;
                    }

                    if (!response.token) {
                        console.error('FCM Subscription does not include a token.');
                        return;
                    }

                    const headers = new Headers();
                    const payload =
                        'subscription=' + response.token + '&' +
                        'pushSet=' + response.pushSet;

                    headers.append('Content-Type', 'application/x-www-form-urlencoded');

                    // Have the Volunteer Portal server associate the created |token| with the user
                    // identified by |userToken|. This requires private authentication information.
                    return fetch('/anime/notifications.php?token=' + userToken, {
                        method: 'POST',
                        headers: headers,
                        body: payload
                    });

                }).then(this.isSubscribed.bind(this)));
            });
        });
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
