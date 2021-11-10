<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Endpoint;

// Allows information about the authenticated user to be obtained, both for verification of
// validity of the authentication token, as for appropriate display of their information in
// the user interface.
//
// See https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apiuser
class UserEndpoint implements Endpoint {
    public function validateInput(array $requestParameters, array $requestData): bool | string {
        if (!array_key_exists('authToken', $requestParameters))
            return 'Missing parameter: authToken';

        return true;  // no input is considered for this endpoint
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        $database = $api->getRegistrationDatabase(/* writable= */ false);
        $environment = $api->getEnvironment();

        $events = array_map(fn($event) => $event->getIdentifier(), $environment->getEvents());

        if ($database) {
            $registrations = $database->getRegistrations();

            foreach ($registrations as $registration) {
                if ($registration->getAuthToken() !== $requestParameters['authToken'])
                    continue;  // non-matching authentication token

                $composedName = $registration->getFirstName() . ' ' . $registration->getLastName();
                $filteredEvents = [];

                foreach ($registration->getEvents() as $eventIdentifier => $participationData) {
                    if (in_array($eventIdentifier, $events))
                        $filteredEvents[$eventIdentifier] = $participationData['role'];
                }

                return [
                    'administrator' => $registration->isAdministrator(),
                    'avatar'        => $registration->getAvatarUrl($environment) ?? '',
                    'events'        => $filteredEvents,
                    'name'          => trim($composedName),
                ];
            }
        }

        return [ /* invalid auth token */ ];
    }
}
