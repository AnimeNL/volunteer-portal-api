<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// Information about the registration for a particular volunteer, even when they haven't been
// formally accepted to the team. Instances should be issues by the VolunteerRegistrationFactory.
class VolunteerRegistration {
    // Constants for the genders recognised by our spreadsheet. Certainly a non-exhaustive list.
    public const GENDER_UNDEFINED = '';
    public const GENDER_MALE = 'M';
    public const GENDER_FEMALE = 'F';
    public const GENDER_OTHER = 'O';

    // Constants for the type of volunteer the registration entails.
    public const TYPE_VOLUNTEER = 'Volunteer';
    public const TYPE_SENIOR = 'Senior';
    public const TYPE_STAFF = 'Staff';

    // Constants for the status of registration requests.
    public const STATUS_NEW = 'New';
    public const STATUS_PENDING = 'Pending';
    public const STATUS_ACCEPTED = 'Accepted';
    public const STATUS_REJECTED = 'Rejected';

    private string $firstName;
    private string $lastName;
    private string $gender = VolunteerRegistration::GENDER_UNDEFINED;
    private string $tshirtSize = '';
    private string $type;
    private string $accessCode;
    private string $emailAddress;
    private string $phoneNumber;
    private string $status;
    private ?bool $hotel = null;
    private ?bool $nightShifts = null;

    // Only used when the database has been opened in read/write mode.
    private $spreadsheetRow = null;

    // ---------------------------------------------------------------------------------------------
    // Data getters
    // ---------------------------------------------------------------------------------------------

    public function getFirstName() : string {
        return $this->firstName;
    }

    public function getLastName() : string {
        return $this->lastName;
    }

    public function getGender() : string {
        return $this->gender;
    }

    public function getTshirtSize() : string {
        return $this->tshirtSize;
    }

    public function getType() : string {
        return $this->type;
    }

    public function getAccessCode() : string {
        return $this->accessCode;
    }

    public function getEmailAddress() : string {
        return $this->emailAddress;
    }

    public function getPhoneNumber() : string {
        return $this->phoneNumber;
    }

    public function getStatus() : string {
        return $this->status;
    }

    public function getHotel() : ?bool {
        return $this->hotel;
    }

    public function getNightShifts() : ?bool {
        return $this->nightShifts;
    }

    // ---------------------------------------------------------------------------------------------
    // Read/write storage helpers
    // ---------------------------------------------------------------------------------------------

    public function getSpreadsheetrow() : int {
        if ($this->spreadsheetRow == null)
            throw new \Exception('The spreadsheet row is only available in read/write mode.');

        return $this->spreadsheetRow;
    }

    public function setSpreadsheetRow(int $row) : void {
        $this->spreadsheetRow = $row;
    }

    // ---------------------------------------------------------------------------------------------

    // Public constructor. Only to be called by the VolunteerRegistrationFactory.
    public function __construct(string $firstName, string $lastName, string $gender,
                                string $tshirtSize, string $type, string $accessCode,
                                string $emailAddress, string $phoneNumber, string $status,
                                ?bool $hotel, ?bool $nightShifts) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->gender = $gender;
        $this->tshirtSize = $tshirtSize;
        $this->type = $type;
        $this->accessCode = $accessCode;
        $this->emailAddress = $emailAddress;
        $this->phoneNumber = $phoneNumber;
        $this->status = $status;
        $this->hotel = $hotel;
        $this->nightShifts = $nightShifts;
    }
}
