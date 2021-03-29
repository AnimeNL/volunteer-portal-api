<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage\Model;

use \Anime\Storage\SecurityToken;

// Values that will be accepted as being "truthy" in the spreadsheet. Lowercase.
const TRUTHY_VALUES = [ 'yes', 'true' ];

// Represents an individual volunteer stored in the registration sheet. Their data is split over a
// sequence of columns, as follows:
//
// A: First name
// B: Last name
// C: Gender (freeform)
// D: Date of birth (YYYY-MM-DD)
// E: E-mail address
// F: Access code
// G: Phone number
// H: Administrator ("Yes", "True"; see TRUTHY_VALUES)
// I, ...: Registration status for a particular event
//
// Because an arbitrary number of events are supported in the same sheet, Registration objects can
// only be created when the RegistrationSheet is known.
class Registration {
    // Number of colums in the shreadsheet that contain fixed data rather than events.
    public const DATA_COLUMN_COUNT = 8;

    private string $firstName;
    private string $lastName;
    private string $gender;
    private string $dateOfBirth;
    private string $emailAddress;
    private string $accessCode;
    private string $phoneNumber;
    private bool $administrator;
    private array $events;

    private string $authToken;
    private string $userToken;

    // Initializes the Registration object. Should only be called by the RegistrationDatabase. It
    // is assumed that validity of the |$spreadsheetRow| has been asserted already.
    public function __construct(array $spreadsheetRow, array $events) {
        $this->firstName = $spreadsheetRow[0];
        $this->lastName = $spreadsheetRow[1];
        $this->gender = $spreadsheetRow[2];
        $this->dateOfBirth = $spreadsheetRow[3];
        $this->emailAddress = $spreadsheetRow[4];
        $this->accessCode = $spreadsheetRow[5];
        $this->phoneNumber = $spreadsheetRow[6];
        $this->administrator = in_array(strtolower($spreadsheetRow[7]), TRUTHY_VALUES);

        $this->events = [];
        foreach ($events as $eventIndex => $eventIdentifier) {
            $valueIndex = $eventIndex + Registration::DATA_COLUMN_COUNT;
            $value = count($spreadsheetRow) > $valueIndex ? $spreadsheetRow[$valueIndex] : null;

            $this->events[$eventIdentifier] = $value ?? 'Unregistered';
        }

        $this->authToken = SecurityToken::GenerateAuthToken($this->accessCode, $this->emailAddress);
        $this->userToken = SecurityToken::GenerateUserToken($this->accessCode, $this->emailAddress);
    }

    // Returns the first name of the person represented in this volunteer registration.
    public function getFirstName(): string {
        return $this->firstName;
    }

    // Returns the last name of the person represented in this volunteer registration.
    public function getLastName(): string {
        return $this->lastName;
    }

    // Returns the gender of this person. This is a freeform string.
    public function getGender(): string {
        return $this->gender;
    }

    // Returns the date of birth of this person, as a string formatted like YYYY-MM-DD.
    public function getDateOfBirth(): string {
        return $this->dateOfBirth;
    }

    // Returns the e-mail address associated with this volunteer registration.
    public function getEmailAddress(): string {
        return $this->emailAddress;
    }

    // Returns the access code through which this registration can be authenticated.
    public function getAccessCode(): string {
        return $this->accessCode;
    }

    // Returns the phone number associated with this volunteer registration.
    public function getPhoneNumnber(): string {
        return $this->phoneNumber;
    }

    // Returns the events through which this registration has a known status.
    public function getEvents(): array {
        return $this->events;
    }

    // Returns whether this registration represents an administrator on the Volunteer Portal.
    public function isAdministrator(): bool {
        return $this->administrator;
    }

    // Returns the authentication token associated with this registration.
    public function getAuthToken(): string {
        return $this->authToken;
    }

    // Returns the user token associated with this registration.
    public function getUserToken(): string {
        return $this->userToken;
    }
}
