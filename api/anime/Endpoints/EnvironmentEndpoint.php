<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Endpoint;

// Allows information to be obtained for the environment the volunteer portal runs under. This
// allows multiple events to be managed by the same instance.
//
// See https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apienvironment
class EnvironmentEndpoint implements Endpoint {
    public function validateInput(array $requestParameters, array $requestData): bool | string {
        return true;  // no input is considered for this endpoint
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        $environment = $api->getEnvironment();
        $events = [];

        foreach ($environment->getEvents() as $event) {
            $events[] = [
                'name'                  => $event->getName(),
                'enableContent'         => $event->enableContent(),
                'enableRegistration'    => $event->enableRegistration(),
                'enableSchedule'        => $event->enableSchedule(),
                'identifier'            => $event->getIdentifier(),
                'timezone'              => $event->getTimezone(),
                'website'               => $event->getWebsite() ?? '',
            ];
        }

        return [
            'title'         => $environment->getTitle(),

            'themeColor'    => $environment->getThemeColor(),
            'themeTitle'    => $environment->getThemeTitle(),

            'events'        => $events,

            'contactName'   => $environment->getContactName(),
            'contactTarget' => $environment->getContactTarget(),
        ];
    }
}
