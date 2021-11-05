<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// Provides the ability to vent Environment instances.
class EnvironmentFactory {
    // Initializes a new environment for the |$hostname| with the given |$configuration|. An empty,
    // invalid environment will be initialized when the configuration is not available.
    public static function createForHostname(
            Configuration $configuration, string $hostname): Environment {
        if (str_starts_with($hostname, 'www.'))
            $hostname = substr($hostname, 4);

        if (!preg_match('/^([a-z0-9]+\.?){2,3}/s', $hostname))
            return new Environment(false);  // invalid format for the |$hostname|.

        $settings = $configuration->get('environments/' . $hostname);
        if ($settings === null)
            return new Environment(false);  // the |$hostname| does not have configuration

        $events = [];
        if (array_key_exists('events', $settings)) {
            foreach ($settings['events'] as $eventIdentifier => $eventOverrides) {
                $eventSettings = $configuration->get('events/' . $eventIdentifier);
                $eventSettings = array_merge($eventSettings, $eventOverrides);

                $event = new Event($eventIdentifier, $eventSettings);
                if (!$event->isValid())
                    continue;

                $events[] = $event;
            }
        }

        return new Environment(true, $hostname, $events, $settings);
    }

    // Returns an array with Environment instances for all environments that have been defined in
    // the configuration file. Both valid and invalid environments will be included.
    public static function getAll(Configuration $configuration): array {
        $hostnames = array_keys($configuration->get('environments'));
        $environments = [];

        foreach ($hostnames as $hostname)
            $environments[] = EnvironmentFactory::createForHostname($configuration, $hostname);

        return $environments;
    }
}
