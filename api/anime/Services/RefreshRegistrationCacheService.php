<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

use Anime\Cache;
use Anime\Configuration;
use Anime\EnvironmentFactory;
use Anime\Storage\RegistrationDatabaseFactory;

// Service that will forcefully update all the cached registration information from the Google Drive
// backend, for each of the configured environments part of the system.
class RefreshRegistrationCacheService extends ServiceBase {
    public function __construct($options, ...$params) {
        parent::__construct(...$params);
    }

    public function execute() : void {
        $cache = Cache::getInstance();

        $configuration = Configuration::getInstance();
        $environments = EnvironmentFactory::getAll($configuration);

        foreach ($environments as $environment) {
            $settings = $environment->getRegistrationDatabaseSettings();
            if (!is_array($settings))
                continue;  // no data has been specified for this environment

            if (!array_key_exists('spreadsheet', $settings) || !array_key_exists('sheet', $settings))
                continue;  // invalid data has been specified for this environment

            $spreadsheetId = $settings['spreadsheet'];
            $sheet = $settings['sheet'];

            $database = RegistrationDatabaseFactory::openReadOnly($cache, $spreadsheetId, $sheet);
            $database->getSheet()->updateCachedRepresentation();
        }
    }
}
