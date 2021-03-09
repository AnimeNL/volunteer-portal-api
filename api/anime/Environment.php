<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// The Environment class represents the context for the application's data sources, for example to
// allow split data sources based on the hostname.
class Environment {
    // Returns an array with Environment instances for all environments that have been defined in
    // the configuration file. Both valid and invalid environments will be included.
    public static function all(Configuration $configuration): array {
        $hostnames = array_keys($configuration->get('environments'));
        $environments = [];

        foreach ($hostnames as $hostname)
            $environments[] = Environment::createForHostname($configuration, $hostname);

        return $environments;
    }

    // Initializes a new environment for the |$hostname| with the given |$configuration|. An empty,
    // invalid environment will be initialized when the configuration is not available.
    public static function createForHostname(
            Configuration $configuration, string $hostname): Environment {
        if (!preg_match('/^([a-z0-9]+\.?){2,3}/s', $hostname))
            return new Environment(false);  // invalid format for the |$hostname|.

        $settings = $configuration->get('environments/' . $hostname);
        if ($settings === null)
            return new Environment(false);  // the |$hostname| does not have configuration

        return new Environment(true, $settings);
    }

    // Initializes a new environment for |$settings|, only intended for use by tests. The |$valid|
    // boolean indicates whether the created environment should be valid.
    public static function createForTests(bool $valid, array $settings): Environment {
        return new Environment($valid, $settings);
    }

    private $valid;

    private $contactName;
    private $contactTarget;
    private $title;

    // Constructor for the Environment class. The |$valid| boolean must be set, and, when set to
    // true, the |$settings| array must be given with all intended options.
    private function __construct(bool $valid, array $settings = []) {
        $this->valid = $valid;

        if (!$valid)
            return;

        $this->contactName = $settings['contactName'];
        $this->contactTarget = $settings['contactTarget'];
        $this->title = $settings['title'];
    }

    // Returns whether this Environment instance represents a valid environment.
    public function isValid(): bool {
        return $this->valid;
    }

    // Returns the name of the person who can be contacted for questions.
    public function getContactName(): string {
        return $this->contactName;
    }

    // Returns the link target of the person who can be contacted for questions, if any.
    public function getContactTarget(): string | null {
        return $this->contactTarget;
    }

    // Returns the name of the Volunteer Portal instance, e.g. Volunteer Portal.
    public function getTitle(): string {
        return $this->title;
    }
}
