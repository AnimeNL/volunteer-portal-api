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
// --> The Registration status can be one of the reserved values (Unregistered, Registered,
//     Cancelled and Rejected), or another value which indicates that the volunteer has been part
//     of the team in a role with the given title.
//
//     Optionally, the number of hours of shifts they had been scheduled for may be included,
//     which must be at the end of the status, as a number surrounded by parenthesis.
//
//     "Steward" -> Role is "Steward", with no indication of hours worked,
//     "Steward (14)" -> Role is "Steward", with 14 hours of performed work.
//
// Because an arbitrary number of events are supported in the same sheet, Registration objects can
// only be created when the RegistrationSheet is known.
class Registration {
    // Number of colums in the shreadsheet that contain fixed data rather than events.
    public const DATA_COLUMN_COUNT = 8;

    // Directory and request path in which avatar information has been stored.
    public const AVATAR_FS_PATH = __DIR__ . '/../../../../avatars/';
    public const AVATAR_URL_PATH = '/avatars/';

    private int $rowNumber;

    private string $firstName;
    private string $lastName;
    private string $gender;
    private ?string $dateOfBirth = null;
    private ?string $emailAddress = null;
    private string $accessCode;
    private ?string $phoneNumber = null;
    private bool $administrator;
    private array $events;

    private string $authToken;
    private string $userToken;

    // Creates the contents of a spreadsheet row for a new registration with the given information.
    // Contained within this file to centralize the logic for the sheet's format.
    public static function CreateSpreadsheetRow(
            array $events, string $event, string $firstName, string $lastName, string $gender,
            string $dateOfBirth, string $emailAddress, string $phoneNumber): array {
        $spreadsheetRow = [
            $firstName,
            $lastName,
            ucfirst($gender),
            $dateOfBirth,
            $emailAddress,
            /* accessCode= */ strval(random_int(1000, 9999)),
            $phoneNumber,
            /* administrator= */ 'No',
        ];

        foreach ($events as $eventIndex => $eventIdentifier) {
            $spreadsheetRow[] = $eventIdentifier === $event ? 'Registered'
                                                            : 'Unregistered';
        }

        return $spreadsheetRow;
    }

    // Initializes the Registration object. Should only be called by the RegistrationDatabase. It
    // is assumed that validity of the |$spreadsheetRow| has been asserted already.
    public function __construct(array $spreadsheetRow, int $rowNumber, array $events) {
        $this->rowNumber = $rowNumber;

        $this->firstName = $spreadsheetRow[0];
        $this->lastName = $spreadsheetRow[1];
        $this->gender = $spreadsheetRow[2];

        if (strlen($spreadsheetRow[3]))
            $this->dateOfBirth = $spreadsheetRow[3];

        if (strlen($spreadsheetRow[4]))
            $this->emailAddress = $spreadsheetRow[4];

        $this->accessCode = $spreadsheetRow[5];

        if (strlen($spreadsheetRow[6]))
            $this->phoneNumber = $spreadsheetRow[6];

        $this->administrator = in_array(strtolower($spreadsheetRow[7]), TRUTHY_VALUES);

        $this->events = [];
        foreach ($events as $eventIndex => $eventIdentifier) {
            $valueIndex = $eventIndex + Registration::DATA_COLUMN_COUNT;
            $value = $spreadsheetRow[$valueIndex] ?? 'Unregistered';

            $matches = null;
            if (preg_match('/^\s*(.*?)\s+\(([0-9]+(\.[0-9]{1,2})?)\)\s*$/s', $value, $matches)) {
                $this->events[$eventIdentifier] = [
                    'role' => $matches[1],
                    'hours' => floatval($matches[2])
                ];
            } else {
                $this->events[$eventIdentifier] = [ 'role' => $value, 'hours' => null ];
            }
        }

        $this->authToken = SecurityToken::GenerateAuthToken($this->accessCode, $this->emailAddress);
        $this->userToken = SecurityToken::GenerateUserToken($this->accessCode, $this->emailAddress);
    }

    // Returns the number of the row on which this registration's information was written.
    public function getRowNumber(): int {
        return $this->rowNumber;
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
    public function getDateOfBirth(): ?string {
        return $this->dateOfBirth;
    }

    // Returns the e-mail address associated with this volunteer registration.
    public function getEmailAddress(): ?string {
        return $this->emailAddress;
    }

    // Returns whether the registration has an e-mail address associated with it.
    public function hasEmailAddress(): bool {
        return is_string($this->emailAddress) && strlen($this->emailAddress) > 0;
    }

    // Returns the access code through which this registration can be authenticated.
    public function getAccessCode(): string {
        return $this->accessCode;
    }

    // Returns the phone number associated with this volunteer registration.
    public function getPhoneNumber(): ?string {
        return $this->phoneNumber;
    }

    // Returns the events through which this registration has a known status. Each event is shared
    // as an associative array with two keys: 'role' and 'hours' (which may be NULL).
    public function getEvents(): array {
        return $this->events;
    }

    // Returns the role this volunteer has in the given |$event| when accepted. This will ignore any
    // of the predetermined roles, such as just having registered.
    public function getEventAcceptedRole(string $event): ?string {
        if (!array_key_exists($event, $this->events))
            return null;  // non-matching event identifier

        $role = $this->events[$event]['role'];
        switch ($role) {
            case 'Unregistered':
            case 'Registered':
            case 'Cancelled':
            case 'Rejected':
            case '':
                return null;
        }

        return $role;
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

    // Returns the path on the server's filesystem where this registration's avatar should reside.
    public function getAvatarFileSystemPath(): string {
        return self::AVATAR_FS_PATH . $this->userToken . '.jpg';
    }

    // Returns the URL to this user's avatar, when it exists.
    public function getAvatarUrl($environment): ?string {
        $avatarPath = $this->getAvatarFileSystemPath();
        $avatarMtime = @ filemtime($avatarPath);

        if (!$avatarMtime)
            return null;

        $hostname = 'https://' . $environment->getHostname();
        $pathname = self::AVATAR_URL_PATH . $this->userToken . '.jpg';

        return  $hostname . $pathname . '?' . $avatarMtime;
    }
}
