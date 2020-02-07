<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// Information about the registration for a particular volunteer, even when they haven't been
// formally added to the team just yet. The expected format for a registration is as follows:
//
// [
//   0  => string  | first name
//   1  => string  | last name
//   2  => string  | gender { M, F }
//   3  => string  | t-shirt size { XS...3XL }
//   4  => null
//   5  => string  | type { Volunteer, Senior Volunteer, Staff }
//   6  => string  | access code
//   7  => string  | e-mail address
//   8  => string  | phone number
//   9  => string  | status { New, Pending, Accepted, Rejected }
//   10 => boolean | hotel
//   11 => boolean | night shifts
// ]
class VolunteerRegistration {
    private $firstName;
    private $lastName;

    // Validates that the given |$registrationEntry| is valid per the format documented in this
    // file. If it's not, it's either because |$registrationEntry| is empty, which we silently
    // ignore, or because it actually contains invalid data.
    public static function Validate(array $registrationEntry) {
        // TODO: Implement validation.
        return true;
    }

    public function __construct(array $registrationEntry) {
        $this->firstName = $registrationEntry[0];
        $this->lastName = $registrationEntry[1];
    }

    // Returns the first name of this volunteer.
    public function getFirstName() : string {
        return $this->firstName;
    }

    // Returns the last name of this volunteer.
    public function getLastName() : string {
        return $this->lastName;
    }
}
