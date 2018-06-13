<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

use \Anime\Environment;
use \Anime\PushNotification;
use \Anime\PushUtilities;

// Service that distributes Push Notifications for the shifts that are about to start. The time
// prior and after the shift during which delivery of the notification will be considered can be
// configured in the configuration file.
class NotificationService implements Service {
    // File to which logs will be written for distributing notifications.
    private const NOTIFICATION_LOG_FILE = __DIR__ . '/notifications.log';

    // Absolute path to the JSON data file that contains the convention's program.
    private const PROGRAM_FILE = __DIR__ . '/../../configuration/program.json';

    // Time, in minutes, that a volunteer will be notified prior to a shift.
    private $reminderTime;

    // Frequency, in minutes, at which this service will be executed.
    private $frequency;

    // File that contains the timestamp at which notification broadcasts were last distributed.
    private $dataFile;

    // The environment for which notifications might have to be broadcasted.
    private $environment;

    // Initializes the service with |$options|, defined in the website's configuration file.
    public function __construct(array $options) {
        if (!array_key_exists('frequency', $options))
            throw new \Exception('The NotificationService requires a `frequency` option.');

        $this->frequency = $options['frequency'];

        if (!array_key_exists('reminderTime', $options))
            throw new \Exception('The NotificationService requires a `reminderTime` option.');

        $this->reminderTime = $options['reminderTime'];

        if (!array_key_exists('data', $options))
            throw new \Exception('The NotificationService requires a `data` option.');

        $this->dataFile = __DIR__ . '/' . $options['data'];

        if (!array_key_exists('context', $options))
            throw new \Exception('The NotificationService requires a `context` option.');

        $this->environment = Environment::createForHostname($options['context']);
        if (!$this->environment->isValid())
            throw new \Exception('The `context` value is set to an invalid value.');
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
        $lastNotificationTime = $this->getAndUpdateLastNotificationTime();
        $notificationTime = $this->getNotificationTime();

        if (!$lastNotificationTime)
            return;  // no notifications have to be send at this time

        // Mapping of volunteer name -> token for volunteers that have to be notified.
        $volunteerTokens = [];

        // Mapping of eventId -> list of volunteer tokens of notifications that need to be send.
        $eventNotifications = [];

        // (1) Assemble the list of events for which volunteers have to be notified.

        $shifts = $this->environment->loadShifts();
        $volunteers = $this->environment->loadVolunteers();

        foreach ($shifts as $volunteerName => $schedule) {
            foreach ($schedule as $shift) {
                if ($shift['shiftType'] !== 'event')
                    continue;  // only events will yield notifications

                if ($shift['beginTime'] < $lastNotificationTime)
                    continue;  // notifications have already been broadcasted

                if ($shift['beginTime'] > $notificationTime)
                    continue;  // notifications are to be broadcasted in the future

                if (!array_key_exists($shift['eventId'], $eventNotifications)) {
                    $eventNotifications[$shift['eventId']] = [
                        'time'      => $shift['beginTime'],
                        'tokens'    => []
                    ];
                }

                // XXXXXXXXXXXX REMOVE BEFORE THE EVENT STARTS XXXXXXXXXXXX
                if ($volunteerName !== 'Sofie Teulings' && $volunteerName !== 'Safae el Hachioui')
                    continue;
                $volunteerName = 'Peter Beverloo';
                // XXXXXXXXXXXX REMOVE BEFORE THE EVENT STARTS XXXXXXXXXXXX

                if (!array_key_exists($volunteerName, $volunteerTokens)) {
                    $volunteer = $volunteers->findByName($volunteerName);
                    if (!$volunteer)
                        throw new \Exception('Invalid volunteer: ' . $volunteerName);

                    $volunteerTokens[$volunteerName] = $volunteer->getToken();
                }

                $eventNotifications[$shift['eventId']]['tokens'][] = $volunteerTokens[$volunteerName];
            }
        }

        if (!count($eventNotifications))
            return;  // no notifications have to be send at this time

        $program = $this->loadProgram();

        // (2) For each of the events that need notifications, find the event information in the
        // program and distribute it to the list of volunteer tokens.

        foreach ($eventNotifications as $eventId => $shift) {
            if (!count($shift['tokens']))
                continue;

            $shift['tokens'] = array_unique($shift['tokens']);

            $event = null;
            $session = null;

            // (2a) Find the event in the |$program| for which the notification is contextual.
            foreach ($program as $programEvent) {
                if ($programEvent['id'] != $eventId)
                    continue;

                $event = $programEvent;
                break;
            }

            if ($event === null)
                throw new \Exception('Cannot locate the appropriate event for #' . $eventId);

            // (2b) Find the appropriate session based on the |$notificationTime|.
            foreach ($event['sessions'] as $programSession) {
                if ($programSession['begin'] > $notificationTime)
                    break;

                // Always store the session furthest in the future.
                $session = $programSession;
            }

            if ($session === null)
                throw new \Exception('Cannot locate the appropriate session for #' . $eventId);

            // (2c) Compile the notification's contents.

            $title = null;
            $message = $session['name'] . ' @ '. $session['location'];

            // Make sure that we format the shift's begin time in the right timezone, regardless of
            // where the server is located.
            {
                $previousTimezone = date_default_timezone_get();
                date_default_timezone_set('Europe/Amsterdam');

                $title = 'Your shift will begin at ' . date('G:i', $shift['time']) . '!';

                date_default_timezone_set($previousTimezone);
            }

            // Compile the PushNotification object.
            $notification = new PushNotification($title, [
                'body'  => $message,
                'url'   => '/volunteers/me/'
            ]);

            // (2d) Distribute the |$notification| the the list of |$shift['tokens']|.

            $results = PushUtilities::sendToTopics($shift['tokens'], $notification, $this->reminderTime * 2);
            file_put_contents(self::NOTIFICATION_LOG_FILE, $results, FILE_APPEND);
        }
    }

    // Loads the full program existing of the common events published through AniPlan, as well as
    // the additional events created for the set of volunteers for the current environment.
    private function loadProgram() : array {
        $program = json_decode(file_get_contents(self::PROGRAM_FILE), true);
        $additions = $this->environment->loadProgram();

        if (!count($additions))
            return $program;

        return array_merge($program, $additions);
    }

    // Gets and then updates the most recent attempted broadcast time for notifications. If the
    // current time in minutes is identical to the last broadcast, or if no time has been stored at
    // all, null will be returned triggering the service to bail out.
    //
    // TODO(peter): We should probably be able to store service-specific state in state.json.
    private function getAndUpdateLastNotificationTime() : ?int {
        $lastNotificationTime = 0;
        $notificationTime = $this->getNotificationTime();

        if (file_exists($this->dataFile))
            $lastNotificationTime = (int) file_get_contents($this->dataFile);

        file_put_contents($this->dataFile, $notificationTime);

        if ($lastNotificationTime < 1496702971 /* random value in the past */)
            return null;  // the |$lastNotificationTime| is too far in the past

        if ($lastNotificationTime >= $notificationTime)
            return null;  // notification have already been distributed

        return $lastNotificationTime;
    }

    // Gets the UNIX timestamp for the current time ceiled to the nearest minute.
    private function getCurrentMinuteTime() : int {
        $offsetForTesting = 3 * 86400;  // 3 days (Tuesday -> Friday)
        return (int) (ceil((time() + $offsetForTesting) / 60) * 60);
    }

    // Gets the UNIX timestamp for the time for which notifications have to be distributed.
    private function getNotificationTime() : int {
        return $this->getCurrentMinuteTime() + $this->reminderTime * 60 /* in seconds */;
    }
}
