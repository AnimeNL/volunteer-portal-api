<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// Represents a Push Notification that can be distributed to volunteers. All options must be passed
// to the constructor which will validate the input. The available options are roughly identical
// to those defined in the Notifications API:
//
// https://notifications.spec.whatwg.org/#dictdef-notificationoptions
//
// The `dir`, `lang`, `sound` and `data` options will be ignored because browsers either don't
// support them, or because they will be used for other internal purposes.
class PushNotification {
    // Array of the options set for this Push Notification. Will be compiled by the constructor.
    private $options;

    public function __construct(string $title, array $options) {
        if (!strlen($title))
            throw new \Exception('The title of a notification must be set.');

        $this->options = [
            'title' => $title,

            // Default option values.
            'badge' => '/images/favicon-64.png',
            'icon'  => '/images/logo-192-2.png',
            'url'   => '/'
        ];

        // String options.
        foreach (['body', 'tag', 'image', 'icon', 'badge', 'url'] as $option) {
            if (!array_key_exists($option, $options))
                continue;

            if (!is_string($options[$option]) || !strlen($options[$option]))
                throw new \Exception('The `' . $option . '` of a notification must be string.');

            $this->options[$option] = $options[$option];
        }

        // Boolean options.
        foreach (['renotify', 'silent', 'requireInteraction'] as $option) {
            if (!array_key_exists($option, $options))
                continue;

            if (!is_bool($options[$option]))
                throw new \Exception('The `' . $option . '` of a notification must be boolean.');

            $this->options[$option] = $options[$option];
        }

        // Freeflow options. (Read: Can't be bothered to validate.)
        foreach (['vibrate', 'timestamp', 'actions'] as $option) {
            if (!array_key_exists($option, $options))
                continue;

            $this->options[$option] = $options[$option];
        }
    }

    // Returns an array with the options for this notification.
    public function getOptions() : array {
        return $this->options;
    }
}
