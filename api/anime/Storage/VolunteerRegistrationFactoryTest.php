<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Storage;

class VolunteerRegistrationFactoryTest extends \PHPUnit\Framework\TestCase {   
    use \Anime\Test\AssertException;
 
    // -------------------------------------------------------------------------
    // Section: Requests
    // -------------------------------------------------------------------------

    // Verifies that requests will be validated appropriately.
    public function testRequestValidation() {
        $this->assertError(function() {
            VolunteerRegistrationFactory::FromRequest(new VolunteerRegistrationRequest());
        });

        $this->assertException(function() {
            $request = new VolunteerRegistrationRequest();
            $request->firstName = '';  // note: empty
            $request->lastName = 'Last';
            $request->accessCode = 'ABCDEF';
            $request->emailAddress = 'user@example.com';
            $request->phoneNumber = '+440000000000';
            $request->nightShifts = true;

            VolunteerRegistrationFactory::FromRequest($request);
        });

        $this->assertException(function() {
            $request = new VolunteerRegistrationRequest();
            $request->firstName = 'First';
            $request->lastName = 'Last';
            $request->accessCode = '';  // note: empty
            $request->emailAddress = 'user@example.com';
            $request->phoneNumber = '+440000000000';
            $request->nightShifts = true;

            VolunteerRegistrationFactory::FromRequest($request);
        });

        $this->assertException(function() {
            $request = new VolunteerRegistrationRequest();
            $request->firstName = 'First';
            $request->lastName = 'Last';
            $request->accessCode = 'ABCDEF';
            $request->emailAddress = '';  // note: empty
            $request->phoneNumber = '+440000000000';
            $request->nightShifts = true;

            VolunteerRegistrationFactory::FromRequest($request);
        });
    }

    // Verifies that created VolunteerRegistration instances reflect their values.
    public function testRequestReflection() {
        $request = new VolunteerRegistrationRequest();
        $request->firstName = 'First';
        $request->lastName = 'Last';
        $request->accessCode = 'ABCDEF';
        $request->emailAddress = 'user@example.com';
        $request->phoneNumber = '+440000000000';
        $request->nightShifts = true;

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
        $request = new VolunteerRegistrationRequest();
        $request->firstName = 'First';
        $request->lastName = 'Last';
        $request->accessCode = 'ABCDEF';
        $request->emailAddress = 'user@example.com';
        $request->phoneNumber = '+440000000000';
        $request->nightShifts = true;

        $registration = VolunteerRegistrationFactory::FromRequest($request);

        $this->assertEquals(VolunteerRegistration::GENDER_UNDEFINED, $registration->getGender());
        $this->assertEquals('', $registration->getTshirtSize());
        $this->assertEquals(VolunteerRegistration::TYPE_VOLUNTEER, $registration->getType());
        $this->assertEquals(VolunteerRegistration::STATUS_NEW, $registration->getStatus());
        $this->assertNull($registration->getHotel());
    }

    // -------------------------------------------------------------------------
    // Section: Spreadsheets
    // -------------------------------------------------------------------------

    // TODO: Implement validation and tests for spreadsheets.

    // -------------------------------------------------------------------------
    // Section: Cache
    // -------------------------------------------------------------------------

    // TODO: Implement factory methods for cached data.
}
