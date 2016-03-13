<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

class ImportProgramServiceTest extends \PHPUnit_Framework_TestCase {
    // Gives us the `assertException` method to allow testing more than a single exception per
    // test method. This really ought to be part of the core PHPUnit assertion suite.
    use \VladaHejda\AssertException;

    // Verifies that the frequency of the service can be configured in the configuration options.
    public function testFrequencyOption() {
        $service = new ImportProgramService([
            'frequency'     => 42
        ]);

        $this->assertEquals(42, $service->getFrequencyMinutes());
    }

    // Verifies that the verification routines throws exceptions when we expect them.
    public function testAssumptionVerificationExceptions() {
        $service = new ImportProgramService([
            'frequency'     => 42
        ]);

        // Assumption: The |$input| data is an array with at least one entry.
        $this->assertException(function () use ($service) {
            $service->validateInputAssumptions([]);

        }, null, null, 'The input must be an array containing at least one entry.');

        // Assumption: All fields that we use in the translation exist in the entries.
        $entry = [
            'name'      => 'My Event',
            'start'     => '2016-03-13T17:00:00+01:00',
            'end'       => '2016-03-13T17:45:00+01:00',
            'location'  => 'Asia',
            'comment'   => null,
            'hidden'    => 0,
            'floor'     => 'floor-0',
            'eventId'   => 42,
            'opening'   => 0
        ];

        foreach (ImportProgramService::REQUIRED_FIELDS as $field) {
            $this->assertTrue(array_key_exists($field, $entry));

            $partialEntry = $entry;
            unset($partialEntry[$field]);

            // The Id included in the error will default to the eventId in the entry, but will fall
            // back to the index of the entry if it's the eventId field that's not known.
            $entryId = $field == 'eventId' ? ':0' : '42';

            $this->assertException(function () use ($service, $partialEntry) {
                $service->validateInputAssumptions([$partialEntry]);

            }, null, null, 'Missing field "' . $field . '" for entry ' . $entryId . '.');
        }

        // Assumption: Entries set to be the `opening` of an event have an associated `closing`.
        $this->assertException(function () use ($service, $entry) {
            $openingOnlyEntry = $entry;
            $openingOnlyEntry['opening'] = 1;

            $service->validateInputAssumptions([$openingOnlyEntry]);

        }, null, null, 'There are opening or closing events without a counter-part.');

        $this->assertException(function () use ($service, $entry) {
            $closingOnlyEntry = $entry;
            $closingOnlyEntry['opening'] = -1;

            $service->validateInputAssumptions([$closingOnlyEntry]);

        }, null, null, 'There are opening or closing events without a counter-part.');

        $this->assertException(function () use ($service, $entry) {
            $invalidValueEntry = $entry;
            $invalidValueEntry['opening'] = 42;

            $service->validateInputAssumptions([$invalidValueEntry]);

        }, null, null, 'Invalid value for "opening" for entry 42.');

        // Assumption: All floors are in the format of "floor-" {-1, 0, 1, 2}.
        $this->assertException(function () use ($service, $entry) {
            $invalidFloorEntry = $entry;
            $invalidFloorEntry['floor'] = 'floor-42';

            $service->validateInputAssumptions([$invalidFloorEntry]);

        }, null, null, 'Invalid value for "floor" for entry 42.');

    }

    // Writes |$data| as JSON to a file, then creates an ImportProgramService instance to parse it,
    // executes the service and reads back the result data from the destination.
    private function importFromData($data) {
        $source = tempnam(sys_get_temp_dir(), 'anime_');
        $destination = tempnam(sys_get_temp_dir(), 'anime_');

        $encodedData = json_encode($data);

        // Write the encoded input |$encodedData| to the |$source| file.
        if (file_put_contents($source, $encodedData) != strlen($encodedData))
            throw new \Exception('Unable to write the $data to a temporary file.');

        try {
            // Create and execute the service using |$source| as the input data.
            $service = new ImportProgramService([
                'destination'   => $destination,
                'frequency'     => 0,
                'source'        => $source
            ]);

            if (!$service->execute())
                throw new \Exception('Unable to execute the ImportProgramService.');

            return json_decode(file_get_contents($destination), true /* associative */);

        } finally {
            unlink($destination);
            unlink($source);
        }
    }
}
