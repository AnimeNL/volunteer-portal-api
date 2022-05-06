<?php
// Copyright 2022 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Endpoint;
use \Anime\EnvironmentFactory;
use \Anime\Storage\NotesDatabase;

// The /api/notes API call enables notes to be requested and stored for volunteers and events. Not
// all users are expected to have write (or even read) access, as controlled by the server.
//
// See https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apinotes
class NotesEndpoint implements Endpoint {
    public function validateInput(array $requestParameters, array $requestData): bool | string {
        if (!array_key_exists('authToken', $requestParameters) || !is_string($requestParameters['authToken']))
            return 'Missing parameter: authToken';

        if (!array_key_exists('event', $requestParameters) || !is_string($requestParameters['event']))
            return 'Missing parameter: event';

        if (!array_key_exists('entityIdentifier', $requestData) || !is_string($requestData['entityIdentifier']))
            return 'Missing data: entityIdentifier';

        if (!array_key_exists('entityType', $requestData) || !is_string($requestData['entityType']))
            return 'Missing data: entityType';

        return true;  // no input is considered for this endpoint
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        $currentEnvironment = $api->getEnvironment();
        $currentEnvironmentHostname = $currentEnvironment->getHostname();

        $registrationDatabase = $api->getRegistrationDatabase(/* $writable= */ false);
        if (!$registrationDatabase)
            return [ 'error' => 'Unable to open the registration database.' ];

        $registration = null;
        $senior = false;

        foreach ($registrationDatabase->getRegistrations() as $registration) {
            if ($registration->getAuthToken() !== $requestParameters['authToken'])
                continue;  // non-matching authentication token

            $role = $registration->getEventAcceptedRole($requestParameters['event']);
            if (!$role)
                continue;  // non-participating authentication token

            $registration = $registration;
            $senior = stripos($role, 'Staff') !== false ||
                      stripos($role, 'Senior') !== false;
        }

        if (!$registration)
            return [ 'error' => 'Unable to validate the authentication token.' ];

        if (!$senior && !$registration->isAdministrator())
            return [ 'error' => 'You are not allowed to update these notes.' ];

        $notesDatabase = NotesDatabase::create($requestParameters['event']);

        $entityType = $requestData['entityType'];
        $entityId = $requestData['entityIdentifier'];
        $notes = $requestData['update'] ?? '';

        if ($notes && strlen($notes))
            $notesDatabase->set($entityType, $entityId, $notes);
        else
            $notesDatabase->delete($entityType, $entityId);

        return [ 'notes' => $notes ];
    }
}
