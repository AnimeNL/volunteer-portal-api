<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Endpoint;
use \Anime\EnvironmentFactory;

// Allows full scheduling information to be requested about a particular event, indicated by the
// `event` request parameter. The returned data is expected to have been (pre)filtered based on
// the access level granted to the owner of the given `authToken`.
//
// TODO: Give environments a "privileged senior" bit or similar in configuration.json5
//
// See https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apievent
class EventEndpoint implements Endpoint {
    public function validateInput(array $requestParameters, array $requestData): bool | string {
        if (!array_key_exists('authToken', $requestParameters))
            return 'Missing parameter: authToken';

        if (!array_key_exists('event', $requestParameters))
            return 'Missing parameter: event';

        return true;
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        $currentEnvironment = $api->getEnvironment();
        $currentEvent = $requestParameters['event'];

        $userRegistration = null;
        $userVisibility = [ $currentEnvironment ];

        // -----------------------------------------------------------------------------------------
        // (1) Find the |$userRegistration| based on the request variables.
        // -----------------------------------------------------------------------------------------

        $currentEnvironmentRegistrationDatabase = $api->getRegistrationDatabase(false);
        if ($currentEnvironmentRegistrationDatabase) {
            foreach ($currentEnvironmentRegistrationDatabase->getRegistrations() as $registration) {
                if ($registration->getAuthToken() !== $requestParameters['authToken'])
                    continue;  // non-matching authentication token

                $role = $registration->getEventAcceptedRole($currentEvent);

                $userRegistration = $registration;
                $userVisibility = $this->getEnvironmentsForRole($api, $role, $currentEvent);
                break;
            }
        }

        // -----------------------------------------------------------------------------------------
        // (2) Load the program and list of events for the |$currentEnvironment|.
        // -----------------------------------------------------------------------------------------

        // TODO...

        // -----------------------------------------------------------------------------------------
        // (3) Iterate over the environments the |$userRegistration| has got access to.
        // -----------------------------------------------------------------------------------------

        $volunteers = [];

        foreach ($userVisibility as $environment) {
            $registrationDatabase = $currentEnvironmentRegistrationDatabase;
            if ($environment !== $currentEnvironment) {
                $registrationDatabase =
                        $api->getRegistrationDatabaseForEnvironment($environment, false);
            }

            if (!$registrationDatabase)
                continue;  // the registration database is not available

            $environmentId = $environment->getThemeTitle();

            // (A) Compile the full list of volunteers participating in the event, on behalf of this
            //     environment. Volunteers carry information about their environments.
            foreach ($registrationDatabase->getRegistrations() as $registration) {
                if ($registration->getEventAcceptedRole($currentEvent) === null)
                    continue;  // non-participating event identifier

                $token = $registration->getUserToken();

                if (array_key_exists($token, $volunteers)) {
                    $volunteers[$token]['environments'][] = $environmentId;
                } else {
                    $volunteers[$token] = [
                        'name'          => [
                            $registration->getFirstName(),
                            $registration->getLastName(),
                        ],
                        'identifier'    => $registration->getUserToken(),
                        'environments'  => [ $environmentId ],
                        'avatar'        => null,
                    ];
                }
            }
        }

        // -----------------------------------------------------------------------------------------
        // (4) Sort, then return the formatted output to the API caller.
        // -----------------------------------------------------------------------------------------

        usort($volunteers, function ($lhs, $rhs) {
            return strcmp($lhs['name'][0], $rhs['name'][0]);
        });

        return [
            'events'        => [],
            'locations'     => [],
            'volunteers'    => $volunteers,
        ];
    }

    // ---------------------------------------------------------------------------------------------

    // Decides on the visible environments based on the given |$role|. Not all roles give access to
    // cross-environment information, whereas others do. Environments which aren't configured for
    // the given |$event| will be ignored.
    private function getEnvironmentsForRole(Api $api, string $role, string $event): array {
        $currentEnvironment = $api->getEnvironment();

        $isStaff = stripos($role, 'Staff') !== false;
        $isSenior = stripos($role, 'Senior') !== false;
        $isStewards = $currentEnvironment->getHostname() === 'stewards.team';

        // (Core) Staff and Senior Stewards have access to all environments. Filter the results from
        // the factory by environments that have activity in the same |$event|.
        if ($isStaff || ($isSenior && $isStewards)) {
            $environments = [];
            foreach (EnvironmentFactory::getAll($api->getConfiguration()) as $environment) {
                foreach ($environment->getEvents() as $environmentEvent) {
                    if ($environmentEvent->getIdentifier() !== $event)
                        continue;

                    $environments[] = $environment;
                    break;
                }
            }

            return $environments;
        }

        return [ $currentEnvironment ];
    }
}
