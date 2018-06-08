<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// The import-program service is responsible for downloading the events and rooms in which events
// will be taking place. The format of the input is entirely proprietary to AnimeCon, so an
// intermediate format has been developed to make adoption for other input types easier.
//
// This service has the following configuration options that must be supplied:
//
//     'destination'  File to which the parsed program information should be written in JSON format.
//
//     'frequency'    Frequency at which to execute the service (in minutes). This value should most
//                    likely be adjusted to run more frequently as the event comes closer.
//
//     'source'       Absolute URL to the JSON source document as described below.
//
// For the Anime 2017 conference, the JSON format has been chosen to serve as the input for this
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
//     'floor'       Floor on which the event takes place. Prefixed with 'floor-'. <digit>.
//     'floorTitle'  Description of the floor on which the event takes place.
//     'tsId'        Internal id of the session of this event in the AnimeCon database.
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
    // Marked as public for testing purposes only.
    public const REQUIRED_FIELDS = ['name', 'start', 'end', 'location', 'comment', 'hidden',
                                    'floor', 'eventId', 'tsId', 'opening'];

    private $options;
    private $ignoredTimeSlots;

    // Initializes the service with |$options|, defined in the website's configuration file.
    public function __construct(array $options) {
        if (!array_key_exists('destination', $options))
            throw new \Exception('The ImportProgramService requires a `destination` option.');

        if (!array_key_exists('frequency', $options))
            throw new \Exception('The ImportProgramService requires a `frequency` option.');

        if (!array_key_exists('source', $options))
            throw new \Exception('The ImportProgramService requires a `source` option.');

        $this->options = $options;
        $this->ignoredTimeSlots =
            array_key_exists('ignored_time_slots', $options) ? $options['ignored_time_slots']
                                                             : [];
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
    public function execute() : void {
        $sourceFile = $this->options['source'];

        $inputData = file_get_contents($sourceFile);
        if ($inputData === false)
            throw new \Exception('Unable to load the source data: ' . $sourceFile);

        $input = json_decode($inputData, true);
        if ($input === null)
            throw new \Exception('Unable to decode the source data as json.');

        // Filters ignored time slot events from the input.
        $input = $this->filterIgnoredTimeSlotEvents($input);

        // Fixes split floor number notations to only mention the lowest floor of the event.
        $this->fixSplitFloorNumbers($input);

        // Will throw an exception when an assumption fails, to make sure that the log files (and
        // the associated alert e-mails) contain sufficient information to push for a fix.
        $this->validateInputAssumptions($input);

        // First merge split entries together into a single entry.
        $this->mergeSplitEntries($input);

        // Translate the |$input| data into our own intermediate program format.
        $program = $this->convertToIntermediateProgramFormat($input);

        $programData = json_encode($program);

        // Write the |$programData| to the destination file indicated in the configuration.
        if (file_put_contents($this->options['destination'], $programData) != strlen($programData))
            throw new \Exception('Unable to write the program data to the destination file.');
    }

    // Filters events from |$input| that have a `tsId` value that's included on the ignore list for
    // time slots (|$ignoredTimeSlots|). Such entries include a reason that is dismissed.
    // This method has public visibility for testing purposes only.
    public function filterIgnoredTimeSlotEvents(array $input) : array {
        $filtered = [];
        $ignored = [];

        foreach ($this->ignoredTimeSlots as $entry)
            $ignored[$entry['tsId']] = true;

        foreach ($input as $entryId => $entry) {
            $tsId = $entry['tsId'];

            if (array_key_exists($tsId, $ignored))
                continue;

            $filtered[] = $entry;
        }

        return $filtered;
    }

    // Merges floor numbers that are in the format of "floor-0-2", which seems to be used to
    // indicate that an event is about to move from floor 0 to 2 (why????), to something sensible.
    // This method has public visibility for testing purposes only.
    public function fixSplitFloorNumbers(array &$input) : void {
        foreach ($input as $entryId => &$entry) {
            $matches = [];

            if (!preg_match('/^floor\-((\-)?\d)\-(\d)$/s', $entry['floor'], $matches))
                continue;

            // Discard everything but the starting floor at which this event exists.
            $entry['floor'] = 'floor-' . $matches[1];
        }
    }

    // Validates the assumptions, as documented in the class-level documentation block, in the data
    // made available per |$input|. Will throw an exception when one of the assumptions fails.
    // This method has public visibility for testing purposes only.
    public function validateInputAssumptions(array $input) : void {
        // Assumption: The |$input| data is an array with at least one entry.
        if (!count($input))
            throw new \Exception('The input must be an array containing at least one entry.');

        $partialEvents = ['openings' => [], 'closings' => []];

        // Assumption: All fields that we use in the translation exist in the entries.
        foreach ($input as $entryId => $entry) {
            // Generate an Id to use in the exception message so that the item can be indicated.
            $eventId = array_key_exists('eventId', $entry) ? $entry['eventId'] : ':' . $entryId;

            foreach (ImportProgramService::REQUIRED_FIELDS as $field) {
                if (array_key_exists($field, $entry))
                    continue;

                throw new \Exception('Missing field "' . $field . '" for entry ' . $eventId . '.');
            }

            // Assumption: All floors are in the format of "floor-" <digit>.
            if (!preg_match('/^floor\-((\-)?\d)$/s', $entry['floor']))
                throw new \Exception('Invalid value for "floor" for entry ' . $eventId . '.');

            switch ($entry['opening']) {
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
                    throw new \Exception('Invalid value for `opening` for event ' . $eventId);
            }
        }

        sort($partialEvents['openings']);
        sort($partialEvents['closings']);

        // Assumption: Entries set to be the `opening` of an event have an associated `closing`.
        if ($partialEvents['openings'] !== $partialEvents['closings'])
            throw new \Exception('There are opening or closing events without a counter-part.');

        // All assumptions have been verified.
    }

    // Merges split entries in |$entries| together into a single entry. The `opening` value will be
    // considered for this, together with the `tsId` value for determining event uniqueness. Any
    // "opening" or "closing" suffix from the event's name will be removed.
    // This method has public visibility for testing purposes only.
    public function mergeSplitEntries(array &$entries) : void {
        $openings = [];

        for ($index = 0; $index < count($entries);) {
            $entry = $entries[$index];
            $tsId = $entry['tsId'];

            if ($entry['opening'] === 1 /* opening */) {
                $openings[$tsId] = $index;
            } else if ($entry['opening'] === -1 /* closing */) {
                if (!array_key_exists($tsId, $openings))
                    throw new \Exception('Unpaired opening/closing sequence for tsId: ' . $tsId);

                $entries[$openings[$tsId]] =
                    $this->mergeEntries($entries[$openings[$tsId]], $entry);
                array_splice($entries, $index, 1);
                continue;
            }

            $index++;
            continue;
        }
    }

    // Merges the |$openingEntry| and |$closingEntry| together into a single entry, modifying the
    // name where applicable, and returns the resulting entry again.
    private function mergeEntries(array $openingEntry, array $closingEntry) : array {
        $eventStart = min(strtotime($openingEntry['start']), strtotime($closingEntry['start']));
        $eventEnd = max(strtotime($openingEntry['end']), strtotime($closingEntry['end']));

        $openingEntry['start'] = date('c', $eventStart);
        $openingEntry['end'] = date('c', $eventEnd);

        $openingEntry['name'] =
            preg_replace('/\s+(opening|closing)\s*$/si', '', $openingEntry['name']);

        return $openingEntry;
    }

    // Converts |$entries| to the intermediate event format used by this portal. It basically takes
    // the naming and values of the AnimeCon format and converts it into something more sensible.
    // This method has public visibility for testing purposes only.
    public function convertToIntermediateProgramFormat(array $entries) : array {
        $events = [];

        // Iterate over all entries which have been merged since, storing them in a series of events
        // each of which have one or multiple sessions.
        foreach ($entries as $entry) {
            // 2018 hack - override settings for Event #41738
            if ($entry['eventId'] == 41738) {
                $entry['name'] = 'Private Event';
                $entry['comment'] = 'Invitation-only event presenting guests with a colourful questionnaire.';
            }
            // ----------------------------------------------

            $session = [
                'name'          => $entry['name'],
                'description'   => $entry['comment'],

                'begin'         => strtotime($entry['start']),
                'end'           => strtotime($entry['end']),

                'location'      => $entry['location'],
                'floor'         => (int) (substr($entry['floor'], 6)),
            ];

            // Coalesce this session with the existing event if it exists.
            if (array_key_exists($entry['eventId'], $events)) {
                $events[$entry['eventId']]['sessions'][] = $session;
                continue;
            }

            $events[$entry['eventId']] = [
                'id'            => $entry['eventId'],
                'hidden'        => !!$entry['hidden'],
                'sessions'      => [ $session ]
            ];
        }

        return array_values($events);
    }
}
