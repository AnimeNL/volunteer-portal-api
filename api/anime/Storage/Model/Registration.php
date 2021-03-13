<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage\Model;

use \Anime\Storage\SecurityToken;

// Represents an individual volunteer stored in the registration sheet. Their data is split over a
// sequence of columns, as follows:
//
// A: First name
// B: Last name
// C: E-mail address
// D: Access code
// E: Phone number
// F, ...: Registration status for a particular event
//
// Because an arbitrary number of events are supported in the same sheet, Registration objects can
// only be created when the RegistrationSheet is known.
class Registration {
    // Number of colums in the shreadsheet that contain fixed data rather than events.
    public const DATA_COLUMN_COUNT = 5;

    private string $firstName;
    private string $lastName;
    private string $emailAddress;
    private string $accessCode;
    private string $phoneNumber;
    private array $events;

    private string $authToken;
    private string $userToken;

    // Initializes the Registration object. Should only be called by the RegistrationDatabase. It
    // is assumed that validity of the |$spreadsheetRow| has been asserted already.
    public function __construct(array $spreadsheetRow, array $events) {
        $this->firstName = $spreadsheetRow[0];
        $this->lastName = $spreadsheetRow[1];
        $this->emailAddress = $spreadsheetRow[2];
        $this->accessCode = $spreadsheetRow[3];
        $this->phoneNumber = $spreadsheetRow[4];

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

    // Returns the authentication token associated with this registration.
    public function getAuthToken(): string {
        return $this->authToken;
    }

    // Returns the user token associated with this registration.
    public function getUserToken(): string {
        return $this->userToken;
    }
}
