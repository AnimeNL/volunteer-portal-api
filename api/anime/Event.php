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
    private string | null $website;

    public function __construct(string $identifier, array $settings) {
        $this->valid = Validation::validateEvent($settings);

        if (!$this->valid)
            return;

        $this->name = $settings['name'];
        $this->enableContent = $settings['enableContent'];
        $this->enableRegistration = $settings['enableRegistration'];
        $this->enableSchedule = $settings['enableSchedule'];
        $this->identifier = $identifier;
        $this->dates = $settings['dates'];
        $this->timezone = $settings['timezone'];
        $this->website = $settings['website'] ?? null;
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

    // Returns the dates during which the event will take place, as an array with two entries:
    // [ /* start= */ "YYYY-MM-DD", /* end= */ "YYYY-MM-DD" ].
    public function getDates(): array {
        return $this->dates;
    }

    // Returns the timezone in which the event takes place, e.g. Europe/London.
    public function getTimezone(): string {
        return $this->timezone;
    }

    // Returns the URL to the website of the broader event.
    public function getWebsite(): string | null {
        return $this->website;
    }
}
