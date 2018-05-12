<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// The ImportTeamService class implements the ability to import a Google Spreadsheet sheet to a
// local data file containing information about a given team.
//
// This service has the following configuration options that must be supplied:
//
//     'destination'  File to which the parsed team information should be written in JSON format.
//
//     'frequency'    Frequency at which to execute the service (in minutes). This value should most
//                    likely be adjusted to run more frequently as the event comes closer.
//
//     'identifier'   Identifier which the service will be identified by, as this code base can be
//                    used to power volunteer portals for e.g. both gophers and stewards.
//
//     'source'       Absolute URL to the published CSV of the Google Spreadsheet meeting the format
//                    restrictions mentioned below.
//
//     'password_salt'  Random string to be used as password salt.
//
// It is important that this sheet follows a consistent format. Consider the following rules when
// setting up a spreadsheet to match this.
//
//     (1) The first row of the data will be ignored (use it for displaying a warning);
//     (2) The rest of the rows expect these exact columns, in order:
//         (a) Full name
//         (b) Type ({ Staff, Senior, Volunteer })
//         (c) E-mail address
//         (d) Telephone number
//
// The parser is strict in regards to these rules, but linient for the actual data values. The
// conditions, as well known mistakes, are tested in the ImportTeamServiceTest.
class ImportTeamService implements Service {
    // Length of the passwords to generate for each person in the team. See the generatePassword()
    // method for an explanation of its use and sensitivity. Public for testing purposes only.
    public const PASSWORD_LENGTH = 4;

    private $options;

    // Initializes the service with |$options|, defined in the website's configuration file.
    public function __construct(array $options) {
        if (!array_key_exists('destination', $options))
            throw new \Exception('The ImportTeamService requires a `destination` option.');

        if (!array_key_exists('frequency', $options))
            throw new \Exception('The ImportTeamService requires a `frequency` option.');

        if (!array_key_exists('identifier', $options))
            throw new \Exception('The ImportTeamService requires an `identifier` option.');

        if (!array_key_exists('source', $options))
            throw new \Exception('The ImportTeamService requires a `source` option.');

        if (!array_key_exists('password_salt', $options))
            throw new \Exception('The ImportTeamService requires a `password_salt` option.');

        $this->options = $options;
    }

    // Returns the identifier for this import service. Defined in the options because multiple teams
    // might be imported if this site has multiple environments.
    public function getIdentifier() : string {
        return $this->options['identifier'];
    }

    // Returns the frequency, in minutes, at which the team should be imported. This is also defined
    // in the options because it will probably be increased as the event comes closer.
    public function getFrequencyMinutes() : int {
        return $this->options['frequency'];
    }

    // Imports the team by parsing data from the source (as set in the options), which must be an
    // exported Google Spreadsheet sheet in CSV format. The class level comment contains more
    // detailed description about the expected data.
    public function execute() : void {
        $sourceFile = $this->options['source'];

        $inputArray = file($sourceFile);
        if ($inputArray === false)
            throw new \Exception('Unable to load the source data: ' . $sourceFile);

        $team = [];

        // Ignore the first line of the input.
        array_shift($inputArray);

        // Iterate over the rest of the lines, parse them eagerly.
        $linenum = 1;
        foreach (array_map('str_getcsv', $inputArray) as $line) {
            $linenum++;
            if (!count($line))
                continue;  // ignore empty lines

            // Only make sure that it's not less than required in order to be forward compatible.
            if (count($line) < 4)
                throw new \Exception('Invalid data line found in: ' . $sourceFile . ':' . $linenum);

            // Full name
            $name = trim($line[0]);

            if (!strlen($name))
                continue;  // ignore lines without names

            // Type ({ Staff, Senior, Volunteer })
            $type = trim($line[1]);

            if (!in_array($type, ['Staff', 'Senior', 'Volunteer']))
                throw new \Exception('Invalid type in "' . $sourceFile . '" for ' . $name);

            // Generate a password for the user.
            $password = $this->generatePassword($name);

            $team[] = [
                'name'      => $name,
                'password'  => $password,
                'type'      => $type,
                'email'     => trim($line[2]),
                'telephone' => trim($line[3])
            ];
        }

        // Sort the team's members by name to guarantee a consistent data file.
        usort($team, function ($lhs, $rhs) {
            return strcmp($lhs['name'], $rhs['name']);
        });

        // Write the resulting |$team| array to the destination file.
        file_put_contents($this->options['destination'], json_encode($team));
    }

    // Generates a password for |$name| by running it through a hashing function and selecting a
    // certain number of characters from it. A salt for the generation can be configured in the
    // service's configuration section.
    //
    // These passwords will only be required for Senior and Staff users who get access to additional
    // information in the application. All other users will be able to log in using their name. In
    // addition, those who require a password can see the passwords of all other users.
    public function generatePassword($name) : string {
        $phrase = base_convert(hash('fnv164', $name . $this->options['password_salt']), 16, 36);
        return strtoupper(substr($phrase, 0, self::PASSWORD_LENGTH));
    }
}
