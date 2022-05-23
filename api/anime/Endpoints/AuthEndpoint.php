<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Endpoint;

// Allows an authentication token (authToken) to be obtained for given credentials. The token
// may have an expiration time, which should be validated on both the client and server-side.
//
// See https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apiauth
class AuthEndpoint implements Endpoint {
    public function validateInput(array $requestParameters, array $requestData): bool | string {
        if (!array_key_exists('emailAddress', $requestData))
            return 'Missing parameter: emailAddress';

        if (!filter_var($requestData['emailAddress'], FILTER_VALIDATE_EMAIL))
            return 'That doesn\'t seem to be a valid e-mail address.';

        if (!array_key_exists('accessCode', $requestData))
            return 'Missing parameter: accessCode';

        return true;  // no input is considered for this endpoint
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        $configuration = $api->getConfiguration();
        $database = $api->getRegistrationDatabase(/* writable= */ false);

        $expirationMinutes = $configuration->get('authentication/sessionTimeoutMinutes');
        $normalizedEmailAddress = strtolower($requestData['emailAddress']);

        if ($database) {
            $registrations = $database->getRegistrations();

            foreach ($registrations as $registration) {
                if ($registration->getEmailAddress() !== $normalizedEmailAddress)
                    continue;  // non-matching e-mail address

                if ($registration->getAccessCode() !== $requestData['accessCode'])
                    continue;  // non-matching access code

                return [
                    'authToken'             => $registration->getAuthToken(),
                    'authTokenExpiration'   => time() + $expirationMinutes * 60,
                ];
            }
        }

        return [ /* invalid credentials */ ];
    }
}
