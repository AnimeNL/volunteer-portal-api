<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

class ImportTeamServiceTest extends \PHPUnit\Framework\TestCase {
    // Trait providing the `assertException` method.
    use \Anime\Test\AssertException;

    // Verifies that the given configuration options will be reflected in the getters.
    public function testOptionGetters() {
        $service = new ImportTeamService([
            'destination'   => '/path/to/destination/file',
            'frequency'     => 42,
            'identifier'    => 'import-team-service',
            'source'        => 'https://data/source.csv',
            'password_salt' => ''
        ]);

        $this->assertEquals(42, $service->getFrequencyMinutes());
        $this->assertEquals('import-team-service', $service->getIdentifier());
    }

    // Verifies that a generic set of information can successfully be imported by the service, as
    // well as the guaranteed alphabetical ordering by full name in the generated file.
    public function testBasicImportTest() {
        $result = $this->importFromData([
            ['John Doe', 'Volunteer', 'john@doe.co.uk', '+447000000000'],
            ['Jane Doe', 'Staff', 'jane@doe.co.uk', '+448000000000']
        ]);

        $this->assertEquals([
            [
                'name'      => 'Jane Doe',
                'password'  => 'LCFP',
                'type'      => 'Staff',
                'email'     => 'jane@doe.co.uk',
                'telephone' => '+448000000000'
            ],
            [
                'name'      => 'John Doe',
                'password'  => '2YB1',
                'type'      => 'Volunteer',
                'email'     => 'john@doe.co.uk',
                'telephone' => '+447000000000'
            ]
        ], $result);
    }

    // Verifies that an invalid value for 'type' will throw an exception.
    public function testTypeValidation() {
        $test = function () {
            $this->importFromData([
                ['John Doe', 'FooType', 'john@doe.co.uk', '+447000000000'],
            ]);
        };

        $this->assertException($test->bindTo($this));
    }

    // Verifies that password can be generated given a name, and that the configurable salt will be
    // honoured in doing so. The passwords are not meant to be personal or secret.
    public function testPasswordGeneration() {
        $firstService = new ImportTeamService([
            'destination'   => '',
            'frequency'     => 0,
            'identifier'    => '',
            'source'        => '',
            'password_salt' => 'heyitssalt'
        ]);

        $secondService = new ImportTeamService([
            'destination'   => '',
            'frequency'     => 0,
            'identifier'    => '',
            'source'        => '',
            'password_salt' => 'anothersalt'
        ]);

        // Passwords should have their expected length.
        $this->assertEquals(
            ImportTeamService::PASSWORD_LENGTH, strlen($firstService->generatePassword('Peter')));
        $this->assertEquals(
            ImportTeamService::PASSWORD_LENGTH, strlen($firstService->generatePassword('Ferdi')));

        // Passwords should be different depending on the name.
        $this->assertNotEquals(
            $firstService->generatePassword('Peter'), $firstService->generatePassword('Ferdi'));

        // Passwords should be different depending on the salt.
        $this->assertNotEquals(
            $firstService->generatePassword('Peter'), $secondService->generatePassword('Peter'));
    }

    // Writes |$data| in CSV form to a file, then creates an ImportTeamService instance to parse it,
    // executes the service and reads back the result data from the destination.
    private function importFromData($data) {
        $source = tempnam(sys_get_temp_dir(), 'anime_');
        $destination = tempnam(sys_get_temp_dir(), 'anime_');

        // Write the input |$data| to the |$source| file.
        {
            $input = fopen($source, 'w');
            fwrite($input, PHP_EOL);  // the first line will be ignored

            foreach ($data as $line)
                fputcsv($input, $line);

            fclose($input);
        }

        try {
            // Create and execute the service using |$source| as the input data.
            $service = new ImportTeamService([
                'destination'   => $destination,
                'frequency'     => 0,
                'identifier'    => 'import-team-service',
                'source'        => $source,
                'password_salt' => ''
            ]);

            $service->execute();

            return json_decode(file_get_contents($destination), true /* associative */);

        } finally {
            unlink($destination);
            unlink($source);
        }
    }
}
