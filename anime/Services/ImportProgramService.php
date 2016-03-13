<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// The import-program service is responsible for downloading the events and rooms in which events
// will be taking place. The format of the input is entirely proprietary to AnimeCon, so an
// intermediate format has been developed to make adoption for other input types easier.
//
// For the Anime 2016 conference, the JSON format has been chosen to serve as the input for this
// website's data. It expects an array of event entries that each have the following fields:
//
//     'name'        Name of the event. May be suffixed with 'opening' or 'closing'.
//     'start'       Start time of the event in full ISO 8601 format.
//     'duration'    Duration, in minutes, of the event. May be zero.
//     'end'         End time of the event in full ISO 8601 format.
//     'type'        Type of event. See below for a list of currently used values.
//     'location'    Name of the location where the event will be taking place.
//     'image'       Image describing the event. Relative to some base URL I haven't found yet.
//     'comment'     Description of the event. May be NULL.
//     'hidden'      Whether the event should be publicly visible.
//     'locationId'  Internal id of the location in the AnimeCon database.
//     'floor'       Floor on which the event takes place. Prefixed with 'floor-'. {-1, 0, 1, 2}.
//     'floorTitle'  Description of the floor on which the event takes place.
//     'tsId'        I have absolutely no idea.
//     'eventId'     Internal id of this event in the AnimeCon database.
//     'opening'     `0` for a one-shot event, `1` for an event's opening, `-1` for its closing.
//
// The current list of values used in the 'type' enumeration:
//
//     compo, concert, cosplaycompo, cosplayevent, event, event18, Internal, lecture, open,
//     themevideolive, workshop
//
// A number of things have to be considered when considering this input format:
//
//     (1) The opening and closing of larger events has been split up in two separate entries.
//     (2) Location names are not to be relied upon and may be changedd at moment's notice. Using
//         the locationId as a unique identifier will be more stable.
//
// Because the input data may change from underneath us at any moment, a validation routing has been
// included in this service that the input must pass before it will be considered for importing.
// Failures will raise an exception, because they will need manual consideration.
class ImportProgramService implements Service {
    // Array containing the fields in a program entry that must be present for this importing
    // service to work correctly. The verification step will make sure that they're all present.
    const REQUIRED_FIELDS = ['name', 'start', 'end', 'location', 'comment', 'hidden', 'floor',
                             'eventId', 'opening'];

    private $options;

    // Initializes the service with |$options|, defined in the website's configuration file.
    public function __construct(array $options) {
        if (!array_key_exists('frequency', $options))
            throw new \Exception('The ImportProgramService requires a `frequency` option.');

        $this->options = $options;
    }

    // Returns a textual identifier for identifying this service.
    public function getIdentifier() : string {
        return 'import-program-service';
    }

    // Returns the frequency at which the service should run. This is defined in the configuration
    // because we may want to run it more frequently as the event comes closer.
    public function getFrequencyMinutes() : int {
        return $this->options['frequency'];
    }

    // Actually imports the program from the API endpoint defined in the options. The information
    // will be distilled per the class-level documentation block's quirks and written to the
    // destination file in accordance with our own intermediate format.
    public function execute() : bool {
        $sourceFile = $this->options['source'];

        $inputData = file_get_contents($sourceFile);
        if ($inputData === false)
            throw new \Exception('Unable to load the source data: ' . $sourceFile);

        $input = json_decode($inputData, true);
        if ($input === null)
            throw new \Exception('Unable to decode the source data as json.');

        // Will throw an exception when an assumption fails, to make sure that the log files (and
        // the associated alert e-mails) contain sufficient information to push for a fix.
        $this->validateInputAssumptions($input);

        // TODO: Actually do something with the |$input|.

        return true;
    }

    // Validates the assumptions, as documented in the class-level documentation block, in the data
    // made available per |$input|. Will throw an exception when one of the assumptions fails.
    // This method has public visibility for testing purposes only.
    public function validateInputAssumptions(array $input) {
        // Assumption: The |$input| data is an array with at least one entry.
        if (!count($input))
            throw new \Exception('The input must be an array containing at least one entry.');

        // Assumption: All fields that we use in the translation exist in the entries.
        foreach ($input as $entryId => $entry) {
            // Generate an Id to use in the exception message so that the item can be indicated.
            $eventId = array_key_exists('eventId', $entry) ? $entry['eventId'] : ':' . $entryId;

            foreach (ImportProgramService::REQUIRED_FIELDS as $field) {
                if (array_key_exists($field, $entry))
                    continue;

                throw new \Exception('Missing field "' . $field . '" for entry ' . $eventId . '.');
            }
        }

        $partialEvents = ['openings' => [], 'closings' => []];

        // Assumption: Entries set to be the `opening` of an event have an associated `closing`.
        foreach ($input as $entry) {
            $eventId = $entry['eventId'];
            switch($entry['opening']) {
                case -1:
                    $partialEvents['closings'][] = $eventId;
                    break;
                case 0:
                    // This is a one-off event entry. No coalescing necessary.
                    break;
                case 1:
                    $partialEvents['openings'][] = $eventId;
                    break;
                default:
                    throw new \Exception('Invalid value for "opening" for entry ' . $eventId . '.');
            }
        }

        sort($partialEvents['openings']);
        sort($partialEvents['closings']);

        if ($partialEvents['openings'] !== $partialEvents['closings'])
            throw new \Exception('There are opening or closing events without a counter-part.');

        // Assumption: All floors are in the format of "floor-" {-1, 0, 1, 2}.
        foreach ($input as $entry) {
            $eventId = $entry['eventId'];
            if (!preg_match('/floor\-(\-1|0|1|2)/s', $entry['floor']))
                throw new \Exception('Invalid value for "floor" for entry ' . $eventId . '.');
        }

        // All assumptions have been verified.
    }
}
