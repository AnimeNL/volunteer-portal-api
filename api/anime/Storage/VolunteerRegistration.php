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
    private string $firstName;
    private string $lastName;
    private string $gender = '';
    private string $tshirtSize = '';
    private string $type;
    private string $accessCode;
    private string $emailAddress;
    private string $phoneNumber;
    private string $status;
    private ?bool $hotel = null;
    private bool $nightShifts;

    private $spreadsheetRow = null;

    // Validates that the given |$registrationEntry| is valid per the format documented in this
    // file. If it's not, it's either because |$registrationEntry| is empty, which we silently
    // ignore, or because it actually contains invalid data.
    public static function Validate(array $registrationEntry) {
        // TODO: Implement validation.
        return true;
    }

    // Creates a new VolunteerRegistration instance based on the |$request| that's guaranteed to
    // validate, as it matches this object's internal structure.
    public static function FromRequest(VolunteerRegistrationRequest $request) : VolunteerRegistration {
        if (!$request->isPopulated())
            throw new \Exception('Not all VolunteerRegistrationRequest fields are populated.');

        $registrationEntry = [
            $request->firstName,
            $request->lastName,
            /* gender= */ '',
            /* t-shirt= */ '',
            /* full name= */ '',
            'Volunteer',
            $request->accessCode,
            $request->emailAddress,
            $request->phoneNumber,
            'New',
            /* hotel= */ '',
            $request->nightShifts ? 'Yes' : 'No',
        ];

        if (!VolunteerRegistration::Validate($registrationEntry))
            throw new \Exception('VolunteerRegistration::FromRequest produced invalid results.');
        
        return new VolunteerRegistration($registrationEntry);
    }

    public function __construct(array $registrationEntry) {
        $this->firstName = $registrationEntry[0];
        $this->lastName = $registrationEntry[1];
        $this->gender = $registrationEntry[2];
        $this->tshirtSize = $registrationEntry[3];
        $this->type = $registrationEntry[5] ?? 'Volunteer';
        $this->accessCode = $registrationEntry[6];
        $this->emailAddress = $registrationEntry[7];
        $this->phoneNumber = $registrationEntry[8];
        $this->status = $registrationEntry[9];

        $this->hotel = strlen($registrationEntry[10]) ? $registrationEntry[10] == 'Yes'
                                                      : null;

        $this->nightShifts = strlen($registrationEntry[11]) ? $registrationEntry[11] == 'Yes'
                                                            : null;
    }

    // Returns the first name of this volunteer.
    public function getFirstName() : string {
        return $this->firstName;
    }

    // Returns the last name of this volunteer.
    public function getLastName() : string {
        return $this->lastName;
    }

    // ---------------------------------------------------------------------------------------------
    
    // Returns the row on the spreadsheet in which the data for this registration is stored. Throws
    // an exception if the value had not been set before.
    public function getSpreadsheetrow() : int {
        if ($this->spreadsheetRow == null)
            throw new \Exception('The spreadsheet row is only available in read/write mode.');

        return $this->spreadsheetRow;
    }

    // Sets |$row| as the row on which the registration is (or should be) stored.
    public function setSpreadsheetRow(int $row) : void {
        $this->spreadsheetRow = $row;
    }

    // Converts this VolunteerRegistration instance to a spreadsheet row.
    public function toSpreadsheetRow() : array {
        return [
            $this->firstName,
            $this->lastName,
            $this->gender,
            $this->tshirtSize,
            /* full name= */ null,
            $this->type,
            $this->accessCode,
            $this->emailAddress,
            $this->phoneNumber,
            $this->status,
            is_null($this->hotel) ? null
                                  : ($this->hotel ? 'Yes' : 'No'),
            is_null($this->nightShifts) ? null
                                        : ($this->nightShifts ? 'Yes' : 'No'),
        ];
    }
}
