<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// The Environment class represents the context for the application's data sources, for example to
// allow split data sources based on the hostname.
class Environment {
    // Directory in which the configuration files for the environments have been stored.
    const CONFIGURATION_DIRECTORY = __DIR__ . '/../configuration/environments/';

    // Initializes a new environment for the |$hostname|. An invalid Environment instance will be
    // returned when there are no known settings for the |$hostname|.
    public static function createForHostname(string $hostname): Environment {
        if (!preg_match('/^([a-z0-9]+\.?){2,3}/s', $hostname))
            return new Environment(false);  // invalid format for the |$hostname|.

        $settingFile = Environment::CONFIGURATION_DIRECTORY . $hostname . '.json';
        if (!file_exists($settingFile) || !is_readable($settingFile))
            return new Environment(false);  // the |$hostname| does not have a configuration file.

        $settingData = file_get_contents($settingFile);
        $settings = json_decode($settingData, true);

        if (!is_array($settings))
            return new Environment(false);  // the configuration file for |$hostname| is invalid.

        return new Environment(true, $settings);
    }

    // Initializes a new environment for |$settings|, only intended for use by tests. The |$valid|
    // boolean indicates whether the created environment should be valid.
    public static function createForTests(bool $valid, array $settings): Environment {
        return new Environment($valid, $settings);
    }

    private $valid;

    private $name;
    private $hostname;

    // Constructor for the Environment class. The |$valid| boolean must be set, and, when set to
    // true, the |$settings| array must be given with all intended options.
    private function __construct(bool $valid, array $settings = []) {
        $this->valid = $valid;

        if (!$valid)
            return;

        $this->name = $settings['name'];
        $this->hostname = $settings['hostname'];
    }

    // Returns whether this Environment instance represents a valid environment.
    public function isValid(): bool {
        return $this->valid;
    }

    // Returns the display name associated with this environment.
    public function getName(): string {
        return $this->name;
    }

    // Returns the canonical hostname (origin) associated with this environment.
    public function getHostname(): string {
        return $this->hostname;
    }
}
