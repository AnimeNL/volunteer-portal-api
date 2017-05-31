<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// Service that distributes Push Notifications for the shifts that are about to start. The time
// prior and after the shift during which delivery of the notification will be considered can be
// configured in the configuration file.
class NotificationService implements Service {
    // Time, in minutes, that a volunteer will be notified prior to a shift.
    private $reminderTime;

    // Frequency, in minutes, at which this service will be executed.
    private $frequency;

    // Initializes the service with |$options|, defined in the website's configuration file.
    public function __construct(array $options) {
        if (!array_key_exists('frequency', $options))
            throw new \Exception('The NotificationService requires a `frequency` option.');

        $this->frequency = $options['frequency'];

        if (!array_key_exists('reminderTime', $options))
            throw new \Exception('The NotificationService requires a `reminderTime` option.');

        $this->reminderTime = $options['reminderTime'];
    }

    // Returns a textual identifier for identifying this service.
    public function getIdentifier() : string {
        return 'notification-service';
    }

    // Returns the frequency at which the service should run.
    public function getFrequencyMinutes() : int {
        return $this->frequency;
    }

    // Considers the schedule to figure out which volunteers need to be notified of upcoming shifts,
    // and then sends push notifications to alert them.
    public function execute() : void {
        // TODO: Implement deciding upon and sending the notifications.
    }
}
