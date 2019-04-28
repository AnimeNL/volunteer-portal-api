<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

class ImportProgramServiceTest extends \PHPUnit\Framework\TestCase {
    // Gives us the `assertException` method to allow testing more than a single exception per
    // test method. This really ought to be part of the core PHPUnit assertion suite.
    use \Anime\Test\AssertException;

    private $storedTimezone;

    protected function setUp() {
        $this->storedTimezone = date_default_timezone_get();
        date_default_timezone_set('Etc/GMT-1');
    }

    protected function tearDown() {
        date_default_timezone_set($this->storedTimezone);
    }

    // Verifies that the frequency of the service can be configured in the configuration options.
    public function testFrequencyOption() {
        $service = new ImportProgramService([
            'destination'   => null,
            'frequency'     => 1337,
            'source'        => null
        ]);

        $this->assertEquals(1337, $service->getFrequencyMinutes());
    }

    // Verifies that the verification routines throws exceptions when we expect them.
    public function testAssumptionVerificationExceptions() {
        $service = $this->createDefaultService();

        // Assumption: The |$input| data is an array with at least one entry.
        $this->assertException(function () use ($service) {
            $service->validateInputAssumptions([]);

        }, null, null, 'The input must be an array containing at least one entry.');

        // Assumption: All fields that we use in the translation exist in the entries.
        $entry = [
            'name'      => 'My Event',
            'start'     => '2017-03-13T17:00:00+01:00',
            'end'       => '2017-03-13T17:45:00+01:00',
            'location'  => 'Asia',
            'comment'   => null,
            'hidden'    => 0,
            'floor'     => 'floor-0',
            'eventId'   => 42,
            'tsId'      => 42,
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

        // Assumption: All floors are in the format of "floor-" {-1, 0, 1, 2}.
        $this->assertException(function () use ($service, $entry) {
            $invalidFloorEntry = $entry;
            $invalidFloorEntry['floor'] = 'floor-42';

            $service->validateInputAssumptions([$invalidFloorEntry]);

        }, null, null, 'Invalid value for "floor" for entry 42.');
    }

    // Verifies that entries that are included on the time slot ignore list will be filtered out.
    public function testFilterIgnoredTimeSlotEvents() {
        $service = new ImportProgramService([
            'destination'           => null,
            'frequency'             => 42,
            'source'                => null,
            'ignored_time_slots'    => [
                [
                    'tsId'      => 1337,
                    'reason'    => 'This entry should be ignored for whatever reason.'
                ]
            ]
        ]);

        $entries = [
            [
                'name'      => 'My first fancy event',
                'start'     => '2017-03-13T22:00:00+01:00',
                'end'       => '2017-03-13T22:00:00+01:00',
                'tsId'      => 42,
                'opening'   => 0 /* event */
            ],
            [
                'name'      => 'Another fancy event',
                'start'     => '2017-03-13T17:00:00+01:00',
                'end'       => '2017-03-13T17:45:00+01:00',
                'tsId'      => 1337,
                'opening'   => 0 /* event */
            ],
            [
                'name'      => 'Third super fancy event',
                'start'     => '2017-03-13T23:00:00+01:00',
                'end'       => '2017-03-13T23:00:00+01:00',
                'tsId'      => 54,
                'opening'   => 0 /* event */
            ]
        ];

        $filtered = $service->filterIgnoredTimeSlotEvents($entries);

        $this->assertEquals(3, count($entries));
        $this->assertEquals(2, count($filtered));

        $this->assertEquals('My first fancy event', $filtered[0]['name']);
        $this->assertEquals('Third super fancy event', $filtered[1]['name']);
    }

    // Verifies that the silly split-floor-number-syntax that was introduced as part of Anime 2017
    // can be fixed into something sensible - the first listed floor number.
    public function testFixSplitFloorNumbers() {
        $service = $this->createDefaultService();
        $entries = [
            [
                'name'      => 'Invalid floor number event',
                'floor'     => 'floor--1-2',
            ],
            [
                'name'      => 'Another silly event',
                'floor'     => 'floor-0-2',
            ]
        ];

        $service->fixSplitFloorNumbers($entries);

        $this->assertEquals([
            [
                'name'      => 'Invalid floor number event',
                'floor'     => 'floor--1',
            ],
            [
                'name'      => 'Another silly event',
                'floor'     => 'floor-0',
            ]
        ], $entries);
    }

    // Verifies that the service has the ability to merge together split entries in the programme,
    // and removes any suffixes from the event name(s) while doing so.
    public function testMergeSplitEntries() {
        $service = $this->createDefaultService();

        // The entries. This contains two events, one of which is split.
        $entries = [
            [
                'name'      => 'Event opening',
                'start'     => '2017-03-13T22:00:00+01:00',
                'end'       => '2017-03-13T22:00:00+01:00',
                'tsId'      => 42,
                'opening'   => 1 /* opening */
            ],
            [
                'name'      => 'Another event opening',
                'start'     => '2017-03-13T17:00:00+01:00',
                'end'       => '2017-03-13T17:45:00+01:00',
                'tsId'      => 84,
                'opening'   => 0 /* event */
            ],
            [
                'name'      => 'Event closing',
                'start'     => '2017-03-13T23:00:00+01:00',
                'end'       => '2017-03-13T23:00:00+01:00',
                'tsId'      => 42,
                'opening'   => -1 /* closing */
            ]
        ];

        // Ask the server to merge the entries for us.
        $service->mergeSplitEntries($entries);

        // Verify that two events are remaining, with the merged one's suffix removed.
        $this->assertEquals([
            [
                'name'      => 'Event',
                'start'     => '2017-03-13T22:00:00+01:00',
                'end'       => '2017-03-13T23:00:00+01:00',
                'tsId'      => 42,
                'opening'   => 1 /* opening */
            ],
            [
                'name'      => 'Another event opening',
                'start'     => '2017-03-13T17:00:00+01:00',
                'end'       => '2017-03-13T17:45:00+01:00',
                'tsId'      => 84,
                'opening'   => 0 /* event */
            ]
        ], $entries);
    }

    // Verifies that the conversion from the AnimeCon data format to our intermediate representation
    // works correctly and still contains the expected information.
    public function testIntermediateFormatConversion() {
        $service = $this->createDefaultService();

        $this->assertEquals([
            [
                'sessions'  => [
                    [
                        'name'          => 'Example event',
                        'description'   => 'Description of event',
                        'begin'         => 1496966400,
                        'end'           => 1496968200,
                        'location'      => 'Asia',
                        'floor'         => -1
                    ]
                ],
                'hidden'    => false,
                'id'        => 42424
            ],
            [
                'sessions'  => [
                    [
                        'name'          => 'Another event',
                        'description'   => 'Description of another event',
                        'begin'         => 1496969100,
                        'end'           => 1496970000,
                        'location'      => 'Atlantic / Dealer Room',
                        'floor'         => 2
                    ]
                ],
                'hidden'    => true,
                'id'        => 84848
            ]
        ], $service->convertToIntermediateProgramFormat([
            [
                'name'      => 'Example event',
                'start'     => '2017-06-09T01:00:00+01:00',
                'end'       => '2017-06-09T01:30:00+01:00',
                'location'  => 'Asia',
                'comment'   => 'Description of event',
                'hidden'    => 0,
                'floor'     => 'floor--1',
                'eventId'   => 42424,
                'opening'   => 0 /* event */
            ],
            [
                'name'      => 'Another event',
                'start'     => '2017-06-09T00:45:00+00:00',
                'end'       => '2017-06-09T01:00:00+00:00',
                'location'  => 'Atlantic / Dealer Room',
                'comment'   => 'Description of another event',
                'hidden'    => 1,
                'floor'     => 'floor-2',
                'eventId'   => 84848,
                'opening'   => 0 /* event */
            ]
        ]));
    }

    // Verifies that the import program services works from end-to-end.
    public function testImportProgramService() {
        $this->assertEquals([
            [
                'sessions'  => [
                    [
                        'name'          => 'Example event',
                        'description'   => 'Description of event',
                        'begin'         => 1496966400,
                        'end'           => 1496970000,
                        'location'      => 'Asia',
                        'floor'         => -1
                    ]
                ],
                'hidden'    => false,
                'id'        => 42424
            ],
            [
                'sessions'  => [
                    [
                        'name'          => 'Another event',
                        'description'   => 'Description of another event',
                        'begin'         => 1496969100,
                        'end'           => 1496970000,
                        'location'      => 'Atlantic / Dealer Room',
                        'floor'         => 2
                    ]
                ],
                'hidden'        => true,
                'id'            => 84848
            ]
        ], $this->importFromData([
            [
                'name'      => 'Example event opening',
                'start'     => '2017-06-09T01:00:00+01:00',
                'end'       => '2017-06-09T01:00:00+01:00',
                'location'  => 'Asia',
                'comment'   => 'Description of event',
                'hidden'    => 0,
                'floor'     => 'floor--1',
                'eventId'   => 42424,
                'tsId'      => 42424,
                'opening'   => 1 /* opening */
            ],
            [
                'name'      => 'Another event',
                'start'     => '2017-06-09T00:45:00+00:00',
                'end'       => '2017-06-09T01:00:00+00:00',
                'location'  => 'Atlantic / Dealer Room',
                'comment'   => 'Description of another event',
                'hidden'    => 1,
                'floor'     => 'floor-2',
                'eventId'   => 84848,
                'tsId'      => 84848,
                'opening'   => 0 /* event */
            ],
            [
                'name'      => 'Example event closing',
                'start'     => '2017-06-09T02:00:00+01:00',
                'end'       => '2017-06-09T02:00:00+01:00',
                'location'  => 'Asia',
                'comment'   => 'Description of event',
                'hidden'    => 0,
                'floor'     => 'floor--1',
                'eventId'   => 42424,
                'tsId'      => 42424,
                'opening'   => -1 /* closing */
            ]
        ]));
    }

    // Verifies that events spanning multiple sessions will end up in the same imported group.
    public function testImportEventSessions() {
        $this->assertEquals([
            [
                'sessions'  => [
                    [
                        'name'          => 'First session',
                        'description'   => 'Description of the first session',
                        'begin'         => 1496966400,
                        'end'           => 1496970000,
                        'location'      => 'Asia',
                        'floor'         => -1
                    ],
                    [
                        'name'          => 'Second session',
                        'description'   => 'Description of the second session',
                        'begin'         => 1496970000,
                        'end'           => 1496973600,
                        'location'      => 'Oceania',
                        'floor'         => -1
                    ]
                ],
                'hidden'    => false,
                'id'        => 42424
            ],
            [
                'sessions'  => [
                    [
                        'name'          => 'Another event',
                        'description'   => 'Description of another event',
                        'begin'         => 1496969100,
                        'end'           => 1496970000,
                        'location'      => 'Atlantic / Dealer Room',
                        'floor'         => 2
                    ]
                ],
                'hidden'    => true,
                'id'        => 84848
            ]
        ], $this->importFromData([
            [
                'name'      => 'First session',
                'start'     => '2017-06-09T01:00:00+01:00',
                'end'       => '2017-06-09T02:00:00+01:00',
                'location'  => 'Asia',
                'comment'   => 'Description of the first session',
                'hidden'    => 0,
                'floor'     => 'floor--1',
                'eventId'   => 42424,
                'tsId'      => 42424,
                'opening'   => 0 /* event */
            ],
            [
                'name'      => 'Another event',
                'start'     => '2017-06-09T00:45:00+00:00',
                'end'       => '2017-06-09T01:00:00+00:00',
                'location'  => 'Atlantic / Dealer Room',
                'comment'   => 'Description of another event',
                'hidden'    => 1,
                'floor'     => 'floor-2',
                'eventId'   => 84848,
                'tsId'      => 84848,
                'opening'   => 0 /* event */
            ],
            [
                'name'      => 'Second session',
                'start'     => '2017-06-09T02:00:00+01:00',
                'end'       => '2017-06-09T03:00:00+01:00',
                'location'  => 'Oceania',
                'comment'   => 'Description of the second session',
                'hidden'    => 0,
                'floor'     => 'floor--1',
                'eventId'   => 42424,
                'tsId'      => 42424,
                'opening'   => 0 /* event */
            ]
        ]));
    }

    // Creates a default instance of the service with void information to make validation happy.
    // Only use this if you won't be using the infrastructure of the service manager.
    private function createDefaultService() : ImportProgramService {
        return new ImportProgramService([
            'destination'   => null,
            'frequency'     => 42,
            'source'        => null
        ]);
    }

    // Writes |$data| as JSON to a file, then creates an ImportProgramService instance to parse it,
    // executes the service and reads back the result data from the destination.
    private function importFromData($data) : array {
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

            $service->execute();

            return json_decode(file_get_contents($destination), true /* associative */);

        } finally {
            unlink($destination);
            unlink($source);
        }
    }
}
