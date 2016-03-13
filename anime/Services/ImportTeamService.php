<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// The ImportTeamService class implements the ability to import a Google Spreadsheet sheet to a
// local data file containing information about a given team.
//
// This service has the following configuration options that must be supplied:
//
//     'frequency'   Frequency at which to execute the service (in minutes). This value should most
//                   likely be adjusted to run more frequently as the event comes closer.
//
//     'identifier'  Identifier which the service will be identified by, as this code base can be
//                   used to power volunteer portals for e.g. both gophers and stewards.
//
//     'source'      Absolute URL to the published CSV of the Google Spreadsheet meeting the format
//                   restrictions mentioned below.
//
// It is important that this sheet follows a consistent format. Consider the following rules when
// setting up a spreadsheet to match this.
//
//     (1) The first row of the data will be ignored (use it for displaying a warning);
//     (2) ...
//
// The parser is strict in regards to these rules, but linient for the actual data values. The
// conditions, as well known mistakes, are tested in the ImportTeamServiceTest.
class ImportTeamService implements Service {
    private $options;

    // Initializes the service with |$options|, defined in the website's configuration file.
    public function __construct(array $options) {
        if (!array_key_exists('frequency', $options))
            throw new \Exception('The ImportTeamService requires a `frequency` option.');

        if (!array_key_exists('identifier', $options))
            throw new \Exception('The ImportTeamService requires an `identifier` option.');

        if (!array_key_exists('source', $options))
            throw new \Exception('The ImportTeamService requires a `source` option.');

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
    public function execute() : bool {
        // TODO: Actually import the team data.
        return true;
    }
}
