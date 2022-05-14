<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime;

// Represents one of the events that has been configured in the configuration file and will be
// taking place at some point. Event instances are specific to an environment, as environments have
// the ability to override common event configuration.
class Event {
    private bool $valid;

    private string $name;
    private bool $enableContent;
    private bool $enableRegistration;
    private bool $enableSchedule;
    private string $identifier;
    private string $timezone;
    private string | null $program;
    private array | null $areas;
    private string | null $website;
    private array | null $scheduleDatabase;

    public function __construct(string $identifier, array $settings) {
        $this->valid = Validation::validateEvent($settings);

        if (!$this->valid)
            return;

        $this->name = $settings['name'];
        $this->enableContent = $settings['enableContent'];
        $this->enableRegistration = $settings['enableRegistration'];
        $this->enableSchedule = $settings['enableSchedule'];
        $this->identifier = $identifier;
        $this->timezone = $settings['timezone'];
        $this->program = $settings['program'] ?? null;
        $this->areas = $settings['areas'] ?? null;
        $this->website = $settings['website'] ?? null;

        if (array_key_exists('scheduleDatabase', $settings))
            $this->scheduleDatabase = $settings['scheduleDatabase'];
    }

    // Returns whether the event's configuration is valid, and can be used.
    public function isValid(): bool {
        return $this->valid;
    }

    // Returns the name of the event, e.g. PortalCon 2021.
    public function getName(): string {
        return $this->name;
    }

    // Returns whether content pages for this event should be enabled.
    public function enableContent(): bool {
        return $this->enableContent;
    }

    // Returns whether volunteer registrations should be accepted for this event.
    public function enableRegistration(): bool {
        return $this->enableRegistration;
    }

    // Returns whether access to the schedule should be enabled for this event.
    public function enableSchedule(): bool {
        return $this->enableSchedule;
    }

    // Returns an URL-safe representation of the event's name, e.g. portalcon-2021.
    public function getIdentifier(): string {
        return $this->identifier;
    }

    // Returns the timezone in which the event takes place, e.g. Europe/London.
    public function getTimezone(): string {
        return $this->timezone;
    }

    // Returns the filename in which the event's program has been written down.
    public function getProgram(): ?string {
        return $this->program;
    }

    // Returns the mappings of area IDs to their names and icons.
    public function getAreas(): ?array {
        return $this->areas;
    }

    // Returns the URL to the website of the broader event.
    public function getWebsite(): ?string {
        return $this->website;
    }

    // Returns the schedule database settings for this event, likely specialized for a particular
    // environment. There should be two keys in the returned object, { spreadsheet, sheet }.
    public function getScheduleDatabaseSettings(): ?array {
        return $this->scheduleDatabase;
    }
}
