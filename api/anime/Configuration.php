<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// The Configuration class provides access to site-wide configuration information. A single instance
// can be obtained by calling the `getInstance()` method. Configuration is immutable.
class Configuration {
    // File that contains the configuration for this installation.
    private const CONFIGURATION_FILE = __DIR__ . '/../configuration/configuration.json5';

    private static $instance;

    // Returns the global instance of the Configuration class. A new instance will be created if
    // none is in existence so far. Unable to read the configuration file is a fatal error.
    public static function getInstance(): Configuration {
        if (self::$instance === null) {
            if (!file_exists(Configuration::CONFIGURATION_FILE))
                throw new \Exception('Unable to open the configuration file: it does not exist.');

            if (!is_readable(Configuration::CONFIGURATION_FILE))
                throw new \Exception('Unable to open the configuration file: it is not readable.');

            $configurationData = file_get_contents(Configuration::CONFIGURATION_FILE);
            $configuration = json5_decode($configurationData, true);

            if (!is_array($configuration))
                throw new \Exception('Unable to open the configuration file: invalid json.');

            self::$instance = new Configuration($configuration);
        }

        return self::$instance;
    }

    // Returns a new instance of the Configuration class fed from |$configuration|. This method
    // should only be used for the purposes of testing.
    public static function createForTests(array $configuration): Configuration {
        return new Configuration($configuration);
    }

    private $configuration;

    // Initializes a new Configuration instance fed from |$configuration|.
    private function __construct(array $configuration) {
        $this->configuration = $configuration;
    }

    // Returns the configuration option that corresponds to |$path|. NULL will be returned when the
    // data with the given |$path| could not be found.
    public function get(string $path) {
        $pathQueue = explode('/', $path);
        $pathCurrent = &$this->configuration;

        while ($key = array_shift($pathQueue)) {
            if (!is_array($pathCurrent) || !array_key_exists($key, $pathCurrent))
                return null;

            $pathCurrent = &$pathCurrent[$key];
        }

        return $pathCurrent;
    }
}
