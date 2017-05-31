<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// Utilities for working with Push Notifications. Each volunteer is represented by a Firebase Cloud
// Messaging topic equal to the hash of their name. The Firebase Cloud Messaging federation service
// is used to enable other Push Services to receive notifications too.
class PushUtilities {
    // Synchronously subscribes the |$subscription| to the given |$topic|. Returns a boolean
    // indicating whether the association could be made successfully.
    public static function subscribeToTopic(string $subscription, string $topic) : bool {
        $configuration = Configuration::getInstance();

        $server_key = $configuration->get('firebase/server_key');
        $url = 'https://iid.googleapis.com/iid/v1/' . $subscription . '/rel/topics/' . $topic;

        $result = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method'    => 'POST',
                'header'    => [
                    'Content-Type: application/json',
                    'Content-Length: 0',
                    'Authorization: key=' . $server_key
                ]
            ]
        ]));

        return trim($result) == '{}';
    }

    // Synchronously sends the |$notification| to the given |$topic|. Returns the response of the
    // push service to which the message was distributed.
    public static function sendToTopic(
        string $topic,
        PushNotification $notification,
        ?int $ttl = null
    ) : string {
        return self::sendToSubscription('/topics/' . $topic, $notification, $ttl);
    }

    // Synchronously sends the |$notification| to the given |$subscription|, which may be the
    // identifier of a topic when using appropriate syntax. Returns the response of the push service
    // to which the message was distributed.
    public static function sendToSubscription(
        string $subscription,
        PushNotification $notification,
        ?int $ttl = null
    ) : string {
        $configuration = Configuration::getInstance();

        $server_key = $configuration->get('firebase/server_key');
        $url = 'https://fcm.googleapis.com/fcm/send';

        $payload = [
            'to'    => $subscription,
            'data'  => $notification->getOptions()
        ];

        if (is_numeric($ttl))
            $payload['time_to_live'] = $ttl;

        return file_get_contents($url, false, stream_context_create([
            'http' => [
                'method'    => 'POST',
                'header'    => [
                    'Content-Type: application/json',
                    'Authorization: key=' . $server_key
                ],
                'content'   => json_encode($payload)
            ]
        ]));
    }
}
