<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// The VolunteerRegistrationRequest object defines all the fields that must be set when creating a
// new registration request. All fields are mandatory, and, due to PHP 7.4 type hints, will throw
// when being accessed without having been initialized.
class VolunteerRegistrationRequest {
    public string $firstName;
    public string $lastName;
    public string $accessCode;
    public string $emailAddress;
    public string $phoneNumber;
    public bool $nightShifts;
}
