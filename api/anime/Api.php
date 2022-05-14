<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

use \Anime\Storage\RegistrationDatabase;
use \Anime\Storage\RegistrationDatabaseFactory;

// Implementation of the actual API calls as methods whose input has been validated for syntax, and
// for whom the appropriate environment is already available.
class Api {
    private Cache $cache;
    private Configuration $configuration;
    private Environment $environment;

    public function __construct(string $hostname) {
        $this->cache = Cache::getInstance();
        $this->configuration = Configuration::getInstance();
        $this->environment = EnvironmentFactory::createForHostname($this->configuration, $hostname);

        if (!$this->environment->isValid())
            throw new \Exception('The "' . $hostname . '" is not known as a valid environment.');
    }

    // ---------------------------------------------------------------------------------------------

    // Returns the cache that should be used.
    public function getCache(): Cache {
        return $this->cache;
    }

    // Returns the configuration instance for the entire volunteer portal.
    public function getConfiguration(): Configuration {
        return $this->configuration;
    }

    // Returns the environment that's applicable to the current hostname.
    public function getEnvironment(): Environment {
        return $this->environment;
    }

    // ---------------------------------------------------------------------------------------------

    // Returns the RegistrationDatabase instance for the current environment. Immutable by default
    // unless the |$writable| argument has been set to TRUE.
    public function getRegistrationDatabase(bool $writable = false): ?RegistrationDatabase {
        return $this->getRegistrationDatabaseForEnvironment($this->environment, $writable);
    }

    // Returns the RegistrationDatabase instance for a given |$environment|. Immutable by default
    // unless the |$writable| argument has been set to TRUE.
    public function getRegistrationDatabaseForEnvironment(
            Environment $environment, bool $writable = false): ?RegistrationDatabase {
        $settings = $environment->getRegistrationDatabaseSettings();
        if (!is_array($settings))
            return null;  // no data has been specified for this environment

        if (!array_key_exists('spreadsheet', $settings) || !array_key_exists('sheet', $settings))
            return null;  // invalid data has been specified for this environment

        $spreadsheetId = $settings['spreadsheet'];
        $sheet = $settings['sheet'];

        if ($writable)
            return RegistrationDatabaseFactory::openReadWrite($this->cache, $spreadsheetId, $sheet);
        else
            return RegistrationDatabaseFactory::openReadOnly($this->cache, $spreadsheetId, $sheet);
    }
}
