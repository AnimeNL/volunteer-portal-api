<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Endpoint;
use \Anime\EnvironmentFactory;
use \Anime\Privileges;
use \Anime\Storage\ScheduleDatabaseFactory;
use \Anime\Storage\ScheduleDatabase;

// Allows a caller to retrieve information about a particular display configured for the event,
// which powers physical devices that can be placed around the convention's venue.
class DisplayEndpoint implements Endpoint {
    public function validateInput(array $requestParameters, array $requestData): bool | string {
        if (!array_key_exists('identifier', $requestData))
            return 'Missing parameter: identifier';

        return true;  // no further input is considered for this endpoint
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        $configuration = $api->getConfiguration();
        $display = $configuration->get('displays/' . $requestData['identifier']);

        if (!is_array($display) || !array_key_exists('event', $display))
            return [ 'error' => 'Invalid display specified' ];

        $scheduleDatabases = [];

        $registrations = [];
        $roles = [];

        $shifts = [];

        // Aggregate all the registrations and shifts for the appropriate event. Information from
        // all of the portal's environments will be considered for this display.
        foreach (Privileges::CROSS_ENVIRONMENT_LIST as $hostname) {
            $environment = EnvironmentFactory::createForHostname($configuration, $hostname);
            if (!$environment->isValid())
                continue;  // the |$environment| is invalid for some reason

            $event = null;

            // (a) Identify the appropriate |$event|
            foreach ($environment->getEvents() as $environmentEvent) {
                if ($environmentEvent->getIdentifier() !== $display['event'])
                    continue;  // the |$environmentEvent| is not in scope

                $event = $environmentEvent;
                break;
            }

            if (!$event)
                continue;  // this |$environment| does not participate in the |$event|

            // (b) Prepare all of the registrations for this particular event so that they can be
            //     added to a cross-environment overview. Some people participate in multiple
            //     environments at the same time, so this helps to de-duplicate participation.
            $registrationDatabase = $api->getRegistrationDatabaseForEnvironment(
                    $environment, /* $writable= */ false);
            if (!$registrationDatabase)
                continue;  // this |$environment| does not have any registrations

            foreach ($registrationDatabase->getRegistrations() as $registration) {
                $role = $registration->getEventAcceptedRole($display['event']);
                if (!$role)
                    continue;  // the |$registration| does not participate in the |$event|

                if ($role === 'Senior' || $role === 'Staff')
                    $role .= ' ' . rtrim($environment->getShortName(), 's');

                $registrations[$registration->getFullName()] = $registration;
                $roles[$registration->getFullName()] = $role;
            }

            // (c) Prepare all of the scheduled shifts for this particular event, and add them to an
            //     array as well. That's the final piece of information
            $settings = $event->getScheduleDatabaseSettings();
            if (!is_array($settings))
                continue;  // no data has been specified for this environment

            $spreadsheetId = $settings['spreadsheet'];

            $mappingSheet = $settings['mappingSheet'];
            $scheduleSheet = $settings['scheduleSheet'];
            $scheduleSheetStartDate = $settings['scheduleSheetStartDate'];

            $scheduleDatabaseId = $spreadsheetId . $mappingSheet . $scheduleSheet;
            if (array_key_exists($scheduleDatabaseId, $scheduleDatabases))
                continue;  // the |$scheduleDatabaseId| has already been processed.

            $scheduleDatabases[$scheduleDatabaseId] = true;
            $scheduleDatabase = ScheduleDatabaseFactory::openReadOnly(
                    $api->getCache(), $spreadsheetId, $mappingSheet, $scheduleSheet,
                    $scheduleSheetStartDate);

            $shiftCodes = [];

            // Support multiple shifts that map to the same eventId. No example of that in the
            // schedule for this year, but it's not impossible to imagine we once might.
            foreach ($scheduleDatabase->getEventMapping() as $shift => $mapping) {
                if ($mapping['eventId'] != $display['eventId'])
                    continue;  // the |$shift| describes something unrelated to this display

                $shiftCodes[$shift] = 1;
            }

            // Retrieve and store each of the shifts part of this schedule, in scope of one of the
            // identifier |$shiftCodes| codes. Then store them.
            foreach ($scheduleDatabase->getScheduledShifts() as $volunteer => $schedule) {
                foreach ($schedule as $scheduledShift) {
                    if ($scheduledShift['type'] !== ScheduleDatabase::STATE_SHIFT)
                        continue;  // the |$scheduledShift| is not associated with an event

                    if (!array_key_exists($scheduledShift['shift'], $shiftCodes))
                        continue;  // the |$scheduledShift| is not associated with our event

                    $shifts[] = [
                        'environment'   => rtrim($environment->getShortName(), 's'),
                        'volunteer'     => $volunteer,

                        'start'         => $scheduledShift['start'],
                        'end'           => $scheduledShift['end'],
                    ];
                }
            }
        }

        // Sort the |$shifts| in ascending order by the time during which they're expected to start,
        // and then by end time to achieve a relatively stable sort.
        usort($shifts, function ($lhs, $rhs) {
            $result = $lhs['start'] - $rhs['start'];
            return $result ? $result
                           : $lhs['end'] - $rhs['end'];
        });

        // Map each of the shifts to complement them with information about the volunteer that will
        // be running the shift if available, or else information derived from the environment.
        $complementedShifts = array_map(function ($shift) use ($api, $display, $registrations, $roles) {
            $shared = [
                'time'  => [ $shift['start'], $shift['end'] ],
            ];

            if (array_key_exists($shift['volunteer'], $registrations)) {
                $registration = $registrations[$shift['volunteer']];
                $avatar = $registration->getAvatarUrl($api->getEnvironment());

                $avatarData = !empty($avatar) ? [ 'avatar' => $avatar ]
                                              : [];

                return [
                    'name'      => $registration->getFirstName(),
                    'role'      => $roles[$shift['volunteer']],

                    ...$avatarData,
                    ...$shared,
                ];
            } else {
                return [
                    'name'      => $shift['volunteer'],
                    'role'      => $shift['environment'],

                    ...$shared,
                ];
            }
        }, $shifts);

        // Return the information to the display according to the API's semantics.
        return [
            'title'     => $display['title'],
            'shifts'    => $complementedShifts,
        ];
    }
}
