<?php
// Copyright 2022 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

use Anime\Cache;
use Anime\Configuration;
use Anime\EnvironmentFactory;
use Anime\Storage\ScheduleDatabaseFactory;

// Service that will forcefully update all the cached schedule information from the Google Drive
// backend, for each of the configured environments part of the system.
class RefreshScheduleCacheService extends ServiceBase {
    private array $eventsToRefresh = [];

    public function __construct($options, ...$params) {
        parent::__construct(...$params);

        if (array_key_exists('eventsToRefresh', $options) && is_array($options['eventsToRefresh']))
            $this->eventsToRefresh = $options['eventsToRefresh'];
    }

    public function execute() : void {
        $cache = Cache::getInstance();

        $configuration = Configuration::getInstance();
        $environments = EnvironmentFactory::getAll($configuration);

        $updated = [];

        foreach ($environments as $environment) {
            $events = $environment->getEvents();
            foreach ($events as $event) {
                if (!in_array($event->getIdentifier(), $this->eventsToRefresh))
                    continue;

                $settings = $event->getScheduleDatabaseSettings();
                if (!is_array($settings))
                    continue;  // no data has been specified for this environment

                if (!array_key_exists('spreadsheet', $settings) ||
                        !array_key_exists('mappingSheet', $settings) ||
                        !array_key_exists('scheduleSheet', $settings)) {
                    continue;  // invalid data has been specified for this environment
                }

                $spreadsheetId = $settings['spreadsheet'];

                $mappingSheet = $settings['mappingSheet'];
                $scheduleSheet = $settings['scheduleSheet'];

                $sheetIdentifier = $spreadsheetId . $mappingSheet . $scheduleSheet;
                if (in_array($sheetIdentifier, $updated))
                    continue;  // already updated
                else
                    $updated[] = $sheetIdentifier;

                $database = ScheduleDatabaseFactory::openReadOnly(
                        $cache, $spreadsheetId, $mappingSheet, $scheduleSheet);

                $database->getMappingSheet()->updateCachedRepresentation();
                $database->getScheduleSheet()->updateCachedRepresentation();
            }
        }
    }
}
