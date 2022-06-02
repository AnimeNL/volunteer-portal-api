<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Endpoint;

// Records that a volunteer has requested advice from Del a Rie advice, so that this can be surfaced
// to Nardo for immediate consultations. Easter egg endpoint.
class NardoEndpoint implements Endpoint {
    public const NARDO_PATH = __DIR__ . '/../../cache/nardo.json';

    public function validateInput(array $requestParameters, array $requestData): bool | string {
        if (!array_key_exists('authToken', $requestParameters))
            return 'Missing parameter: authToken';

        return true;  // no further input is considered for this endpoint
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        $database = $api->getRegistrationDatabase(/* writable= */ false);
        if (!$database)
            return [ /* no registration database is available */ ];

        foreach ($database->getRegistrations() as $registration) {
            if ($registration->getAuthToken() !== $requestParameters['authToken'])
                continue;  // non-matching authentication token

            $requests = [];
            if (file_exists(self::NARDO_PATH) && is_readable(self::NARDO_PATH))
                $requests = json_decode(file_get_contents(self::NARDO_PATH), /* assoc= */ true);

            $requests[$registration->getUserToken()] = time();

            if (is_writable(self::NARDO_PATH))
                file_put_contents(self::NARDO_PATH, json_encode($requests));

            break;
        }

        return [ /* request completed */ ];
    }
}
