<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Storage;

class VolunteerRegistrationFactoryTest extends \PHPUnit\Framework\TestCase {   
    use \Anime\Test\AssertException;
 
    // -------------------------------------------------------------------------
    // Utility functions
    // -------------------------------------------------------------------------

    private function createValidRegistrationRequest() : VolunteerRegistrationRequest {
        $request = new VolunteerRegistrationRequest();
        $request->firstName = 'First';
        $request->lastName = 'Last';
        $request->accessCode = 'ABCDEF';
        $request->emailAddress = 'user@example.com';
        $request->phoneNumber = '+440000000000';
        $request->nightShifts = true;

        return $request;
    }

    private function createValidSpreadsheetRow() : array {
        return [
            /* firstName= */ 'First',
            /* lastName= */ 'Last',
            /* gender= */ VolunteerRegistration::GENDER_MALE,
            /* tshirtSize= */ 'L',
            /* displayName= */ 'First Last',
            /* type= */ VolunteerRegistration::TYPE_VOLUNTEER,
            /* accessCode= */ 'KMFNEZ',
            /* emailAddress= */ 'user@example.com',
            /* phoneNumber= */ '+440000000000',
            /* status=*/ VolunteerRegistration::STATUS_NEW,
            /* hotel= */ '',
            /* nightShifts= */ ''
        ];
    }

    // -------------------------------------------------------------------------
    // Section: Requests
    // -------------------------------------------------------------------------

    // Verifies that requests will be validated appropriately.
    public function testRequestValidation() {
        $this->assertError(function() {
            VolunteerRegistrationFactory::FromRequest(new VolunteerRegistrationRequest());
        });

        $this->assertException(function() {
            $request = $this->createValidRegistrationRequest();
            $request->firstName = '';  // note: empty

            VolunteerRegistrationFactory::FromRequest($request);
        });

        $this->assertException(function() {
            $request = $this->createValidRegistrationRequest();
            $request->accessCode = '';  // note: empty

            VolunteerRegistrationFactory::FromRequest($request);
        });

        $this->assertException(function() {
            $request = $this->createValidRegistrationRequest();
            $request->emailAddress = '';  // note: empty

            VolunteerRegistrationFactory::FromRequest($request);
        });
    }

    // Verifies that created VolunteerRegistration instances reflect their values.
    public function testRequestReflection() {
        $request = $this->createValidRegistrationRequest();
        $registration = VolunteerRegistrationFactory::FromRequest($request);

        $this->assertEquals($request->firstName, $registration->getFirstName());
        $this->assertEquals($request->lastName, $registration->getLastName());
        $this->assertEquals($request->accessCode, $registration->getAccessCode());
        $this->assertEquals($request->emailAddress, $registration->getEmailAddress());
        $this->assertEquals($request->phoneNumber, $registration->getPhoneNumber());
        $this->assertEquals($request->nightShifts, $registration->getNightShifts());
    }

    // Verifies the default values which will be automatically set for requests.
    public function testRequestDefaultValues() {
        $request = $this->createValidRegistrationRequest();
        $registration = VolunteerRegistrationFactory::FromRequest($request);

        $this->assertEquals(
            $request->firstName . ' ' . $request->lastName, $registration->getDisplayName());

        $this->assertEquals(VolunteerRegistration::GENDER_UNDEFINED, $registration->getGender());
        $this->assertEquals('', $registration->getTshirtSize());
        $this->assertEquals(VolunteerRegistration::TYPE_VOLUNTEER, $registration->getType());
        $this->assertEquals(VolunteerRegistration::STATUS_NEW, $registration->getStatus());
        $this->assertNull($registration->getHotel());
    }

    // -------------------------------------------------------------------------
    // Section: Spreadsheets
    // -------------------------------------------------------------------------

    // Verifies that spreadsheet data will be validated appropriately.
    public function testSpreadsheetValidation() {
        $this->assertNull(VolunteerRegistrationFactory::FromSpreadsheetRow([]));

        $this->assertException(function() {
            $values = $this->createValidSpreadsheetRow();
            $values[5] = 'Super Special Volunteer';  // note: invalid type

            VolunteerRegistrationFactory::FromSpreadsheetRow($values);
        });

        $this->assertException(function() {
            $values = $this->createValidSpreadsheetRow();
            $values[6] = '';  // note: empty access code

            VolunteerRegistrationFactory::FromSpreadsheetRow($values);
        });

        $this->assertException(function() {
            $values = $this->createValidSpreadsheetRow();
            $values[7] = '';  // note: empty e-mail address

            VolunteerRegistrationFactory::FromSpreadsheetRow($values);
        });

        $this->assertException(function() {
            $values = $this->createValidSpreadsheetRow();
            $values[9] = 'Alienated';  // note: invalid status

            VolunteerRegistrationFactory::FromSpreadsheetRow($values);
        });
    }

    // Verifies that created VolunteerRegistration instances reflect their values.
    public function testspreadsheetReflection() {
        $values = $this->createValidSpreadsheetRow();
        $registration = VolunteerRegistrationFactory::FromSpreadsheetRow($values);

        $this->assertNotNull($registration);

        $this->assertEquals($values[0], $registration->getFirstName());
        $this->assertEquals($values[1], $registration->getLastName());
        $this->assertEquals($values[4], $registration->getDisplayName());
        $this->assertEquals($values[2], $registration->getGender());
        $this->assertEquals($values[3], $registration->getTshirtSize());
        $this->assertEquals($values[5], $registration->getType());
        $this->assertEquals($values[6], $registration->getAccessCode());
        $this->assertEquals($values[7], $registration->getEmailAddress());
        $this->assertEquals($values[8], $registration->getPhoneNumber());
        $this->assertEquals($values[9], $registration->getStatus());
        $this->assertNull($registration->getHotel());
        $this->assertNull($registration->getNightShifts());
    }

    // Verifies that spreadsheet data can round-trip back through (de)serialization.
    public function testSpreadsheetRoundTrip() {
        $originalValues = $this->createValidSpreadsheetRow();
        $registration = VolunteerRegistrationFactory::FromSpreadsheetRow($originalValues);

        $serializedValues = VolunteerRegistrationFactory::ToSpreadsheetRow($registration);
        $serializedValues[4] = $originalValues[4];  // display name is spreadsheet dictated

        $this->assertEquals($originalValues, $serializedValues);
    }

    // -------------------------------------------------------------------------
    // Section: Cache
    // -------------------------------------------------------------------------

    // TODO: Implement factory methods for cached data.
}
