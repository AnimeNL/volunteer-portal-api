<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Endpoint;

// Allows static content to be obtained for the registration sub-application, as well as other
// pages that can be displayed on the portal. The <App> component is responsible for routing.
//
// See https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apicontent
class ContentEndpoint implements Endpoint {
    public function validateInput(array $requestParameters, array $requestData): bool | string {
        return true;  // no input is considered for this endpoint
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        return [
            'pages' => $api->getEnvironment()->getContent(),
        ];
    }
}
