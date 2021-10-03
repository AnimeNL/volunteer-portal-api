<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// Base class for services, which implements the appropriate interface. Provides functionality to
// store and retrieve the identifier and frequency for this particular implementation.
abstract class ServiceBase implements Service {
    protected function __construct(private int $frequencyMinutes,
                                   private string $identifier) {}

    // Returns a textual identifier unique to this service. Should be URL safe.
    public function getIdentifier(): string {
        return $this->identifier;
    }

    // Returns the frequency, in minutes, at which this service should be executed.
    public function getFrequencyMinutes(): int {
        return $this->frequencyMinutes;
    }
}
