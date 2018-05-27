<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// Service responsible for importing the schedule, as well as mappings from the schedule entries to
// the program entries they belong to, to a more sensible intermediate format.
class ImportScheduleService implements Service {
    private $options;

    // Initializes the service with |$options|, defined in the website's configuration file.
    public function __construct(array $options) {
        $required = ['frequency', 'identifier', 'volunteers', 'mapping', 'schedule', 'prefix',
                     'destination_shifts', 'destination_team_program'];

        foreach ($required as $option) {
            if (!array_key_exists($option, $options))
                throw new \Exception('The ImportTeamService requires a `' . $option . '` option.');
        }

        $this->options = $options;
    }

    public function getIdentifier() : string {
        return $this->options['identifier'];
    }

    public function getFrequencyMinutes() : int {
        return $this->options['frequency'];
    }

    public function execute() : void {
        $volunteers = $this->loadVolunteers();
        $schedule = $this->loadSchedule();
        $mapping = $this->loadMapping();

        $programAdditions = [];
        $programAdditionId = $this->options['prefix'];

        // (1) Process new entries in the mapping and create new program events for them, to make
        // sure that they can show up using the regular front-end infrastructure.
        foreach ($mapping as $id => &$map) {
            if ($map['eventId'] != '-')
                continue;  // this mapping already has an event Id assigned to it.

            $map['eventId'] = $programAdditionId;

            $programAdditions[$programAdditionId] = [
                'id'        => $programAdditionId,
                'data'      => $map,
                'shifts'    => []
            ];

            ++$programAdditionId;
        }

        $volunteerShifts = [];

        // (2) Iterate over all volunteers in the |$schedule|, resolve their name based on the data
        // available in |$volunteers| and build the |$volunteerShifts| based on that information.
        foreach ($schedule as $abbreviatedName => $shiftData) {
            $name = $this->resolveName($abbreviatedName, $volunteers);
            $shifts = [];

            foreach ($shiftData as $beginTime => $type) {
                $endTime = $beginTime + 3600;  // one-hour blocks

                if (substr($type, 0, 1) == '#')
                    $beginTime += 1800;
                if (substr($type, -1, 1) == '#')
                    $endTime -= 1800;

                $type = trim($type, '#');

                $shiftType = null;
                $eventId = null;

                // Special-case available and not available times in the schedule.
                switch ($type) {
                    case 'x':
                        $shiftType = 'unavailable';
                        break;
                    case '':
                        $shiftType = 'available';
                        break;
                    default:
                        $shiftType = 'event';
                        if (!array_key_exists($type, $mapping))
                            throw new \Exception('Invalid shift type: ' . $type);

                        $eventId = $this->resolveEventId($mapping[$type]['eventId'], $beginTime);
                        break;
                }

                $shifts[] = [
                    'shiftType'     => $shiftType,
                    'eventId'       => $eventId,

                    'beginTime'     => $beginTime,
                    'endTime'       => $endTime
                ];
            }

            $volunteerShifts[$name] = $shifts;
        }

        // (3) For each volunteer, merge shifts that span more than an hour together into a single
        // shift. Report these shifts as sessions when a new |$programAdditions| entry was created.
        foreach ($volunteerShifts as $name => $shifts) {
            $normalizedShifts = [];

            for ($i = 0; $i < count($shifts); ++$i) {
                $shift = $shifts[$i];

                for ($j = $i + 1; $j < count($shifts); ++$j) {
                    if ($shifts[$j]['shiftType'] !== $shift['shiftType'] ||
                        $shifts[$j]['eventId'] !== $shift['eventId'])
                        break;  // this is a different shift

                    $shift['endTime'] = $shifts[$j]['endTime'];
                    ++$i;
                }

                if (array_key_exists($shift['eventId'], $programAdditions))
                    $programAdditions[$shift['eventId']]['shifts'][] = $shift;

                $normalizedShifts[] = $shift;
            }

            $volunteerShifts[$name] = $normalizedShifts;
        }

        // (4) Build the formal events and sessions for the program additions, as they would be
        // created by the program import service (defined by the AnimeCon API itself).
        foreach ($programAdditions as $eventId => $entry) {
            $data = $entry['data'];
            $sessions = [];

            if (count($entry['shifts']) == 0) {
                unset($programAdditions[$eventId]);
                continue;
            }

            usort($entry['shifts'], function ($lhs, $rhs) {
                if ($lhs['beginTime'] === $rhs['beginTime'])
                    return 0;
                return $lhs['beginTime'] > $rhs['beginTime'] ? 1 : -1;
            });

            for ($i = 0; $i < count($entry['shifts']); ++$i) {
                $shift = $entry['shifts'][$i];

                for ($j = $i + 1; $j < count($entry['shifts']); ++$j) {
                    if ($entry['shifts'][$j]['beginTime'] > $shift['endTime'])
                        break;  // this is a different shift

                    $shift['endTime'] = $entry['shifts'][$j]['endTime'];
                    ++$i;
                }

                $sessions[] = [
                    'name'          => $data['name'],
                    'description'   => $data['description'],
                    'begin'         => $shift['beginTime'],
                    'end'           => $shift['endTime'],
                    'location'      => $data['location'],
                    'floor'         => (int) $data['floor']
                ];
            }

            $programAdditions[$eventId] = [
                'id'        => $eventId,
                'hidden'    => false,
                'sessions'  => $sessions
            ];
        }

        // (5) Store the now finalized data in two files in the configuration/ directory, as defined
        // by the configuration of this service.
        file_put_contents($this->options['destination_shifts'], json_encode($volunteerShifts));
        file_put_contents(
            $this->options['destination_team_program'], json_encode(array_values($programAdditions)));
    }

    // Loads the list of volunteers from the configured data file.
    private function loadVolunteers() : array {
        return json_decode(file_get_contents($this->options['volunteers']), true);
    }

    // Loads the schedule from the live exported CSV representation of the schedule spreadsheet.
    private function loadSchedule() : array {
        $scheduleLines = file($this->options['schedule']);
        $schedule = [];

        // The following constants have been derived by looking at the spreadsheet in the browser.
        $SKIP_HEADER_ROWS = 2;
        $NAME_COLUMN = 0 /* A */;
        $SCHEDULE_BEGIN = 1 /* B */;
        $SCHEDULE_BEGIN_TIME = 1529056800 /* 10am, Friday June 15th, 2018 UTC */ - 2 * 3600 /* CEST */;
        $SCHEDULE_END = 57 /* BF */;

        for ($i = $SKIP_HEADER_ROWS; $i < count($scheduleLines); ++$i) {
            $scheduleLine = str_getcsv(trim($scheduleLines[$i]));
            if (count($scheduleLine) <= $SCHEDULE_END)
                continue;  // not enough data on the line

            if (empty($scheduleLine[$NAME_COLUMN]))
                continue;  // the non-empty validation check failed

            $name = trim($scheduleLine[$NAME_COLUMN]);
            $shifts = [];

            for ($j = $SCHEDULE_BEGIN; $j <= $SCHEDULE_END; ++$j) {
                $time = $SCHEDULE_BEGIN_TIME + ($j - $SCHEDULE_BEGIN) * 3600;
                $shifts[$time] = trim($scheduleLine[$j]);
            }

            $schedule[$name] = $shifts;
        }

        return $schedule;
    }

    // Loads the event mappings from the live exported CSV representation of the schedule sheet.
    private function loadMapping() : array {
        $mappingLines = file($this->options['mapping']);
        $mapping = [];

        for ($i = 1 /* skip the header */; $i < count($mappingLines); ++$i) {
            $mappingLine = str_getcsv(trim($mappingLines[$i]));
            if (count($mappingLine) < 6)
                continue;

            $mapping[$mappingLine[0]] = [
                'eventId'       => $mappingLine[1],
                'name'          => $mappingLine[2],
                'location'      => $mappingLine[3],
                'floor'         => $mappingLine[4],
                'description'   => $mappingLine[5]
            ];
        }

        return $mapping;
    }

    // Resolves the |$abbreviatedName| based on the list of |$volunteers|. Returns their full name.
    private function resolveName($abbreviatedName, $volunteers) : string {
        $results = [];

        $normalizedName = rtrim(strtolower($abbreviatedName), '.');
        $normalizedNameLength = strlen($normalizedName);

        foreach ($volunteers as $volunteer) {
            if (substr(strtolower($volunteer['name']), 0, $normalizedNameLength) == $normalizedName)
                $results[] = $volunteer['name'];
        }

        if (!count($results))
            throw new \Exception('There are no known volunteers for "' . $abbreviatedName . '".');

        if (count($results) > 1)
            throw new \Exception('Multiple volunteers are known for "' . $abbreviatedName . '".');

        return $results[0];
    }

    // Resolves the event Id for the configured |$eventId|. This is necessary because certain events
    // on the schedule represent multiple events in the program.
    private function resolveEventId($eventId, $time) : int {
        if (is_numeric($eventId))
            return (int) $eventId;

        // Return the appropriate Event ID for the |$eventId| here.
        //
        // This normally means that the volunteer schedule groups together an event that has several
        // entries in the AniPlanner. In such cases the actual Event ID should be hardcoded here
        // based on the given |$time|.

        switch ($eventId) {
            case 'SPECIAL1':  // Live house
                if ($time <= strtotime('2018-06-16 12:00:00'))
                    return 41598;  // Speakerheadz
                else if ($time <= strtotime('2018-06-16 14:00:00'))
                    return 41558;  // Necronomidol
                else if ($time <= strtotime('2018-06-16 15:40:00'))
                    return 41557;  // Numb'n'Dub
                else if ($time <= strtotime('2018-06-16 17:00:00'))
                    return 41586;  // Savage States
                else if ($time <= strtotime('2018-06-16 19:30:00'))
                    return 41556;  // X Made Alcoholic Santaclaus
                else if ($time <= strtotime('2018-06-17 15:30:00'))
                    return 41559;  // Dress Up Town
                break;

            case 'SPECIAL2':  // Whisky tasting
                if ($time <= strtotime('2018-06-15 22:00:00'))
                    return 41493;  // The Taste of Japanese Whisky 8th Edition
                else if ($time <= strtotime('2018-06-17 16:00:00'))
                    return 41603;  // Taste of Japanese Whisky Anniversary Edition
                break;
        }

        throw new \Exception('Unrecognized event Id: ' . $eventId);
    }
}
