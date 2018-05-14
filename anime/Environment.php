<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// The Environment class represents the context for the application's data sources, for example to
// allow split data sources based on the hostname.
class Environment {
    // Directory in which the configuration files for the environments have been stored.
    // Marked public for testing purposes only.
    public const CONFIGURATION_DIRECTORY = __DIR__ . '/../configuration/environments/';

    // Directory in which information about the individual teams has been stored.
    private const TEAM_DATA_DIRECTORY = __DIR__ . '/../configuration/teams/';

    // Directory in which the generic information is stored.
    private const DATA_DIRECTORY = __DIR__ . '/../configuration/';

    // Initializes a new environment for the |$hostname|. An invalid Environment instance will be
    // returned when there are no known settings for the |$hostname|.
    public static function createForHostname(string $hostname) : Environment {
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
    public static function createForTests(bool $valid, array $settings) : Environment {
        return new Environment($valid, $settings);
    }

    // Loads all the existing configurations, keyed by the hostname they can be loaded with.
    public static function getAll() : array {
        $filenames = glob(Environment::CONFIGURATION_DIRECTORY . '*.json');
        $environments = [];

        foreach ($filenames as $filename) {
            $filename = str_replace(Environment::CONFIGURATION_DIRECTORY, '', $filename);
            $hostname = str_replace('.json', '', $filename);

            $environment = Environment::createForHostname($hostname);
            if (!$environment->isValid())
                continue;

            $environments[$hostname] = $environment;
        }

        return $environments;
    }

    private $valid;

    private $team;
    private $program;
    private $shifts;

    private $name;
    private $shortName;
    private $hostname;
    private $teamDataFile;
    private $teamProgramFile;
    private $teamShiftsFile;
    private $year;

    // Constructor for the Environment class. The |$valid| boolean must be set, and, when set to
    // true, the |$settings| array must be given with all intended options.
    private function __construct(bool $valid, array $settings = []) {
        $this->valid = $valid;

        if (!$valid)
            return;

        $this->team = null;

        $this->name = $settings['name'];
        $this->shortName = $settings['short_name'];
        $this->titles = $settings['titles'];
        $this->hostname = $settings['hostname'];
        $this->hiddenEventsPublic = $settings['hidden_events_public'];
        $this->teamDataFile = $settings['team_data'];
        $this->teamProgramFile = $settings['team_program'];
        $this->teamShiftsFile = $settings['team_shifts'];
        $this->year = $settings['year'];
    }

    // Returns whether this Environment instance represents a valid environment.
    public function isValid() : bool {
        return $this->valid;
    }

    // Returns the display name associated with this environment.
    public function getName() : string {
        return $this->name;
    }

    // Returns the short name of the environment, that can be used for display purposes.
    public function getShortName() : string {
        return $this->shortName;
    }

    // Returns the Environment-specific title associated with a volunteer's type.
    public function typeToTitle(string $type) : string {
        if (!array_key_exists($type, $this->titles))
            return $type;

        return $this->titles[$type];
    }

    // Returns the canonical hostname (origin) associated with this environment.
    public function getHostname() : string {
        return $this->hostname;
    }

    // Returns whether hidden events are to be made visible for all volunteers, regardless of level.
    public function areHiddenEventsPublic() : bool {
        return $this->hiddenEventsPublic;
    }

    // Loads the list of volunteers associated with this environment and returns a VolunteerList
    // instance. The instance will be cached, so multiple calls will return the same instance.
    public function loadVolunteers() : VolunteerList {
        if ($this->team === null) {
            $teamData = file_get_contents(self::TEAM_DATA_DIRECTORY . $this->teamDataFile);
            $this->team = VolunteerList::create(json_decode($teamData, true));
        }

        return $this->team;
    }

    // Loads the program additions that are relevant to this team. These are entries dynamically
    // created based on the schedule the team's staff has created.
    public function loadProgram() : array {
        if ($this->program === null) {
            $this->program =
                json_decode(file_get_contents(self::DATA_DIRECTORY . $this->teamProgramFile), true);
        }

        return $this->program;
    }

    // Loads the shifts for the volunteers that are part of this team.
    public function loadShifts() : array {
        if ($this->shifts === null) {
            $this->shifts =
                json_decode(file_get_contents(self::DATA_DIRECTORY . $this->teamShiftsFile), true);
        }

        return $this->shifts;
    }

    // Returns the year for which this environment has been created.
    public function getYear() : int {
        return $this->year;
    }
}
