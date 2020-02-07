<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// Converts |$value| to either null (the empty string) or a boolean.
function toNullableBool(string $value): ?bool {
    return strlen($value) ? $value == 'Yes'
                          : null;
}

// Converts |$value| to a string representing the nullable boolean.
function toBooleanString(?bool $value): string {
    if (is_null($value))
        return '';
    
    return $value ? 'Yes' : 'No';
}

// The VolunteerRegistrationFactory encapsulates the knowledge on how to (de)serialize volunteer
// registration entries, separate from our internal representation. Any instances should be issued
// by one of the static methods available on this factory.
//
// * [todo: cache]
// * Use VolunteerRegistrationFactory::FromRequest() when you have a VolunteerRegistrationRequest.
// * Use VolunteerRegistrationFactory::FromSpreadsheetRow() when loading from the live spreadsheet.
//
// * [todo: cache]
// * Use VolunteerRegistrationFactory::ToSpreadsheetRow() when writing to the spreadsheet.
//
// Registrations are stored in the spreadsheet as follows:
//
// [
//   0  => string   | first name
//   1  => string   | last name
//   2  => ?string  | gender { M, F }
//   3  => ?string  | t-shirt size { XS...3XL }
//   4  => null
//   5  => string   | type { Volunteer, Senior Volunteer, Staff }
//   6  => string   | access code
//   7  => string   | e-mail address
//   8  => string   | phone number
//   9  => string   | status { New, Pending, Accepted, Rejected }
//   10 => ?boolean | hotel
//   11 => ?boolean | night shifts
// ]
class VolunteerRegistrationFactory {
    // Creates a new VolunteerRegistration instance based on the |$request| that's guaranteed to
    // validate, as it matches this object's internal structure.
    public static function FromRequest(VolunteerRegistrationRequest $request) : VolunteerRegistration {
        VolunteerRegistrationFactory::AssertValidRequest($request);

        return new VolunteerRegistration($request->firstName,
                                         $request->lastName,
                                         VolunteerRegistration::GENDER_UNDEFINED,
                                         /* tshirtSize= */ '',
                                         VolunteerRegistration::TYPE_VOLUNTEER,
                                         $request->accessCode,
                                         $request->emailAddress,
                                         $request->phoneNumber,
                                         VolunteerRegistration::STATUS_NEW,
                                         /* hotel= */ null,
                                         $request->nightShifts);
    }

    // Creates a new VolunteerRegistration instance based on the |$values|, which must represent the
    // horizontal cells on a row in the Registration spreadsheet.
    public static function FromSpreadsheetRow(array $values) : ?VolunteerRegistration {
        if (VolunteerRegistrationFactory::IsEmptySpreadsheetRow($values))
            return null;
        
        VolunteerRegistrationFactory::AssertValidSpreadsheet($values);

        return new VolunteerRegistration(/* firstName= */ $values[0],
                                         /* lastName= */ $values[1],
                                         /* gender= */ $values[2],
                                         /* tshirtSize= */ $values[3],
                                         /* type= */ $values[5],
                                         /* accessCode= */ $values[6],
                                         /* emailAddress= */ $values[7],
                                         /* phoneNumber= */ $values[8],
                                         /* status=*/ $values[9],
                                         /* hotel= */ toNullableBool($values[10]),
                                         /* nightShifts= */ toNullableBool($values[11]));
    }

    // Converts the given |$registration| to an array that can be written to the spreadsheet.
    public static function ToSpreadsheetRow(VolunteerRegistration $registration) : array {
        return [
            $registration->getFirstName(),
            $registration->getLastName(),
            $registration->getGender(),
            $registration->getTshirtSize(),
            /* fullName= */ null,
            $registration->getType(),
            $registration->getAccessCode(),
            $registration->getEmailAddress(),
            $registration->getPhoneNumber(),
            $registration->getStatus(),
            toBooleanString($registration->getHotel()),
            toBooleanString($registration->getNightShifts()),
        ];
    }

    // ---------------------------------------------------------------------------------------------

    // Asserts that the |$request| has valid and non-null fields in it.
    private static function AssertValidRequest(VolunteerRegistrationRequest $request) {
        if (!strlen($request->firstName))
            throw new \Exception('A non-empty first name is required for a request.');
        
        if (!strlen($request->accessCode))
            throw new \Exception('A non-empty access code is required for a request.');

        if (!strlen($request->emailAddress))
            throw new \Exception('A non-empty e-mail address is required for a request.');
    }

    // Returns whether the |$values| row represents an empty, unused row on the spreadsheet.
    private static function IsEmptySpreadsheetRow(array $values) : bool {
        return false;
    }

    // Asserts that |$values| has valid and non-null fields in it.
    private static function AssertValidSpreadsheet(array $values) {
    }
}
