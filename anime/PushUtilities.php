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
        return self::sendToTargets([ '/topics/' . $topic ], $notification, $ttl);
    }

    // Synchronously sends the |$notification| to the given |$topics|. Returns the response of the
    // push service to which the message was distributed.
    public static function sendToTopics(
        array $topics,
        PushNotification $notification,
        ?int $ttl = null
    ) : string {
        $targets = array_map(function ($topic) {
            return '/topics/' . $topic;
        }, $topics);

        return self::sendToTargets($targets, $notification, $ttl);
    }

    // Synchronously sends the |$notification| to the given |$subscription|, which may be the
    // identifier of a topic when using appropriate syntax. Returns the response of the push service
    // to which the message was distributed.
    public static function sendToSubscription(
        string $subscription,
        PushNotification $notification,
        ?int $ttl = null
    ) : string {
        return self::sendToTargets([ $subscription ], $notification, $ttl);
    }

    // Synchronously sends the |$notification| to the given |$subscriptions|, which may be the
    // identifier of a topic when using appropriate syntax. Returns the response of the push service
    // to which the message was distributed.
    public static function sendToSubscriptions(
        array $subscriptions,
        PushNotification $notification,
        ?int $ttl = null
    ) : string {
        return self::sendToTargets($subscriptions, $notification, $ttl);
    }

    // Synchronously sends the |$notification| to the given |$targets|, which is an array with the
    // subscriptions and/or the topics of the recipients. Returns the response of the push service
    // to which the message was distributed.
    private static function sendToTargets(
        array $targets,
        PushNotification $notification,
        ?int $ttl = null
    ) : string {
        $configuration = Configuration::getInstance();

        $server_key = $configuration->get('firebase/server_key');
        $url = 'https://fcm.googleapis.com/fcm/send';

        $payload = [ 'data'  => $notification->getOptions() ];
        if (is_numeric($ttl))
            $payload['time_to_live'] = $ttl;

        // The context owning all parallel CURL requests.
        $context = curl_multi_init();
        $requests = [];

        foreach ($targets as $target) {
            $request = curl_init();

            $requestPayload = $payload;
            $requestPayload['to'] = $target;

            curl_setopt_array($request, [
                CURLOPT_URL             => $url,
                CURLOPT_POST            => true,
                CURLOPT_HTTPHEADER      => [
                    'Content-Type: application/json',
                    'Authorization: key=' . $server_key
                ],
                CURLOPT_POSTFIELDS      => json_encode($requestPayload),

                CURLOPT_HEADER          => true,
                CURLOPT_RETURNTRANSFER  => true
            ]);

            curl_multi_add_handle($context, $request);
            $requests[] = [$request, time(), $target];
        }

        // Finish all the requests simultaneously by first executing the handles, then selecting
        // handles until their execution has been completed.

        $active = null;
        $state = CURLM_CALL_MULTI_PERFORM;

        while ($state === CURLM_CALL_MULTI_PERFORM) {
            $state = curl_multi_exec($context, $active);
        }

        while ($state === CURLM_OK && $active) {
            if (curl_multi_select($context) === -1)
                usleep(1);

            $state = CURLM_CALL_MULTI_PERFORM;

            while ($state === CURLM_CALL_MULTI_PERFORM) {
                $state = curl_multi_exec($context, $active);
            }
        }

        // Compile the output from all parallel requests as if there was only one.

        $output = '';

        foreach ($requests as [$request, $time, $target]) {
            $output .= 'MESSAGE [ ' . date('Y-m-d H:i:s', $time) . ' to ' . $target . ']' . PHP_EOL . PHP_EOL;
            $output .= curl_multi_getcontent($request) . PHP_EOL . PHP_EOL;
            curl_multi_remove_handle($context, $request);
        }

        // Close all remaining CURL handles and return the |$output|.

        curl_multi_close($context);
        return $output;
    }
}
