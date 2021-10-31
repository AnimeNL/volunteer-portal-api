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
// See https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apievent
class EventEndpoint implements Endpoint {
    private const PRIVILEGE_NONE = 0;
    private const PRIVILEGE_ALL = PHP_INT_MAX - 1;

    // Privileges that can be assigned to individuals based on their access level, role, team and
    // whether or not they've been marked as an administrator.
    private const PRIVILEGE_ACCESS_CODES = 1;
    private const PRIVILEGE_CROSS_ENVIRONMENT = 2;
    private const PRIVILEGE_PHONE_NUMBERS = 4;

    // List of hosts whose seniors have the ability to access volunteers of the other environments.
    private const CROSS_ENVIRONMENT_HOSTS_ALLOWLIST = [ 'stewards.team '];

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

        $userEnvironments = [ /* empty by default */ ];
        $userPrivileges = self::PRIVILEGE_NONE;
        $userRegistration = null;

        // -----------------------------------------------------------------------------------------
        // (1) Find the |$userRegistration| based on the request variables.
        // -----------------------------------------------------------------------------------------

        $currentEnvironmentRegistrationDatabase = $api->getRegistrationDatabase(false);
        if ($currentEnvironmentRegistrationDatabase) {
            foreach ($currentEnvironmentRegistrationDatabase->getRegistrations() as $registration) {
                if ($registration->getAuthToken() !== $requestParameters['authToken'])
                    continue;  // non-matching authentication token

                $userRegistration = $registration;

                // Administrators have all access, so skip the additional access checks.
                if ($registration->isAdministrator()) {
                    $userPrivileges = self::PRIVILEGE_ALL;
                    break;
                }

                $role = $registration->getEventAcceptedRole($currentEvent);

                // Access to phone numbers is limited to Staff and Senior members of our volunteer
                // force, whereas cross-environment access is limited to an allowlist.
                $isStaff = stripos($role, 'Staff') !== false;
                $isSenior = stripos($role, 'Senior') !== false;

                $isCrossEnvironmentAllowedHost = in_array(
                        $currentEnvironment->getHostname(), self::CROSS_ENVIRONMENT_HOSTS_ALLOWLIST);

                if ($isStaff || $isSenior)
                    $userPrivileges |= self::PRIVILEGE_PHONE_NUMBERS;

                if (($isStaff || $isSenior) && $isCrossEnvironmentAllowedHost)
                    $userPrivileges |= self::PRIVILEGE_CROSS_ENVIRONMENT;

                break;
            }

            // Assign the set of visible environments to |$userEnvironments| based on whether their
            // registration was found, and whether they've got the CROSS_ENVIRONMENT privilege.
            if ($userRegistration) {
                $userEnvironments = [ $currentEnvironment ];

                if ($userPrivileges & self::PRIVILEGE_CROSS_ENVIRONMENT) {
                    foreach (EnvironmentFactory::getAll($api->getConfiguration()) as $environment) {
                        if ($environment->getHostname() === $currentEnvironment->getHostname())
                            continue;

                        foreach ($environment->getEvents() as $environmentEvent) {
                            if ($environmentEvent->getIdentifier() !== $currentEvent)
                                continue;

                            $userEnvironments[] = $environment;
                            break;
                        }
                    }
                }
            }
        }

        // -----------------------------------------------------------------------------------------
        // (2) Iterate over the environments the |$userRegistration| has got access to.
        // -----------------------------------------------------------------------------------------

        $volunteers = [];

        foreach ($userEnvironments as $environment) {
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
                    continue;
                }

                $information = [
                    'name'          => [
                        $registration->getFirstName(),
                        $registration->getLastName(),
                    ],
                    'identifier'    => $registration->getUserToken(),
                    'environments'  => [ $environmentId ],
                ];

                // Supplement privileged information to the volunteer's entry when allowed.
                if ($userPrivileges & self::PRIVILEGE_ACCESS_CODES)
                    $information['accessCode'] = $registration->getAccessCode();

                if ($userPrivileges & self::PRIVILEGE_PHONE_NUMBERS)
                    $information['phoneNumner'] = $registration->getPhoneNumber();

                // Supplement information about the user's avatar when it can be found on the file-
                // system. At some point we should optimize this check somehow.
                $avatarFile = $registration->getUserToken() . '.jpg';
                $avatarPath = Api::AVATAR_PATH . $avatarFile;

                if (file_exists(Api::AVATAR_DIRECTORY . $avatarFile))
                    $information['avatar'] = 'https://' . $environment->getHostname() . $avatarPath;

                // Store the compiled |$information| structure for the current volunteer.
                $volunteers[$token] = $information;
            }
        }

        // -----------------------------------------------------------------------------------------
        // (3) Load the program and list of events for the |$currentEnvironment|.
        // -----------------------------------------------------------------------------------------

        // TODO...

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
}
