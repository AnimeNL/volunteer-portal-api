<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// The VolunteerRegistrationRequest object defines all the fields that must be set when creating a
// new registration request.
class VolunteerRegistrationRequest {
    public string $firstName;
    public string $lastName;
    public string $accessCode;
    public string $emailAddress;
    public string $phoneNumber;
    public bool $nightShifts;

    // Validates that all mandatory fields have a non-null value. PHP 7.4 type checking will further
    // cause an exception to be thrown when any of the fields haven't been accessed yet.
    public function isPopulated() : bool {
        return $this->firstName != null &&
               $this->lastName != null &&
               $this->accessCode != null &&
               $this->emailAddress != null &&
               $this->phoneNumber != null &&
               $this->nightShifts != null;
    }
}
