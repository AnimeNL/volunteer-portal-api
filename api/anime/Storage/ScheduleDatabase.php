<?php
// Copyright 2022 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

use Anime\Storage\Backend\GoogleSheet;
use Anime\Storage\Backend\GoogleSheetCache;

// Provides access to the schedules assigned to volunteers, which is backed by a Google Sheets file.
// Multiple environments may share a single sheet, so lookups generally should be done based on the
// name of the volunteer.
class ScheduleDatabase {
    // Volunteers can be in one of three states according to the schedule. AVAILABLE means that they
    // are assumed to be on site, but not on duty. UNAVAILABLE means that we should avoid contacting
    // them. SHIFT means that they're currently on shift somewhere.
    public const STATE_UNAVAILABLE = 0;
    public const STATE_AVAILABLE = 1;
    public const STATE_SHIFT = 2;

    // Special state that's used internally when availability of the next state is unknown.
    private const STATE_AVAILABILITY_UNKNOWN = 3;

    private GoogleSheet $mappingSheet;
    private GoogleSheet $scheduleSheet;
    private int $scheduleSheetStartDate;

    private ?array $mapping = null;
    private ?array $schedule = null;

    public function __construct(
            GoogleSheet $mappingSheet, GoogleSheet $scheduleSheet, string $scheduleSheetStartDate) {
        $this->mappingSheet = $mappingSheet;
        $this->scheduleSheet = $scheduleSheet;
        $this->scheduleSheetStartDate = strtotime($scheduleSheetStartDate);
    }

    public function getMappingSheet(): GoogleSheet {
        return $this->mappingSheet;
    }

    public function getScheduleSheet(): GoogleSheet {
        return $this->scheduleSheet;
    }

    // ---------------------------------------------------------------------------------------------
    // Schedule Database API
    // ---------------------------------------------------------------------------------------------

    // Retrieves the event mapping to apply for the shifts. Each shift identifier is represented in
    // the returned array, remaining shifts are dropped.
    public function getEventMapping(): array {
        $this->initializeIfNeeded();
        return $this->mapping;
    }

    // Retrieves the scheduled shifts for this particular event. Cheap to call multiple times. Each
    // shift is associated with a defined mapping, whereas mappings will be created for shifts that
    // are completely misconfigured and we've got no idea what to do with.
    public function getScheduledShifts(): array {
        $this->initializeIfNeeded();
        return $this->schedule;
    }

    // ---------------------------------------------------------------------------------------------
    // Internal functionality
    // ---------------------------------------------------------------------------------------------

    // Initializes the schedule database if needed. Will be a no-op when already initialized.
    private function initializeIfNeeded(): void {
        if (!$this->mapping)
            $this->initializeMapping();

        if (!$this->schedule)
            $this->initializeSchedule();
    }

    // Initializes the event mapping contained within the mapping sheet. It's a simple sheet that
    // contains a list of shift identifiers, to the shift's name, event ID and/or location ID.
    private function initializeMapping(): void {
        $mappingData = $this->mappingSheet->getRange('A3:F999');
        if (!is_array($mappingData) || !count($mappingData))
            throw new \Exception('Unable to read mapping data from the schedule database.');

        $this->mapping = [];
        foreach ($mappingData as $mappingEntry) {
            if (count($mappingEntry) < 6)
                $mappingEntry += array_fill(0, 6 - count($mappingEntry), /* empty string= */ '');

            $this->mapping[$mappingEntry[0]] = [
                'description'   => $mappingEntry[1],
                'eventId'       => strval($mappingEntry[2]),
                'areaId'        => strval($mappingEntry[3]),
                'locationId'    => strval($mappingEntry[4]),
                'locationName'  => $mappingEntry[5],
            ];
        }
    }

    // Initializes the shift schedule for this event. This is a rather complex sheet that lists each
    // volunteer, followed by a cell for each hour the festival is due to take place. The value of
    // the cell defines whether or not the volunteer is active, or even available.
    private function initializeSchedule(): void {
        $scheduleData = $this->scheduleSheet->getRange('A2:ZZ999');
        if (!is_array($scheduleData) || !count($scheduleData))
            throw new \Exception('Unable to read schedule data from the schedule database.');

        // The header is used to calculate the number of cells we'll consider for the event. Rows
        // that either have an empty A cell, or don't have >= this number of cells are discarded.
        $header = array_shift($scheduleData);
        $headerCount = count($header);

        $this->schedule = [];
        foreach ($scheduleData as $volunteerScheduleData) {
            if (count($volunteerScheduleData) < $headerCount || !strlen($volunteerScheduleData[0]))
                continue;  // invalid row

            $volunteerName = array_shift($volunteerScheduleData);
            $volunteerSchedule = ScheduleDatabase::composeSchedule(
                $this->scheduleSheetStartDate, $volunteerScheduleData,
                $headerCount - /* shifted name cell= */ 1);

            $this->schedule[$volunteerName] = $volunteerSchedule;
        }
    }

    // Composes the schedule for an individual volunteer. This is a complex process where each of
    // the individual timeslots will be combined into a number of continuous shifts. Public in order
    // to enable a unit test specifically for this functionality.
    public static function composeSchedule(
            int $scheduleStartTime, array $timeslots, ?int $timeslotLimit = null): array {
        $currentShift = null;
        $currentState = null;

        // Time the final time slot has finished. Needed outside of the loop to finalize the final
        // |$currentShift| entry at the end of this volunteer's schedule.
        $timeslotEndTime = null;

        //
        // carry-over time

        $schedule = [];
        foreach ($timeslots as $timeslot => $activity) {
            if ($timeslotLimit && $timeslot >= $timeslotLimit)
                break;  // timeslot is out of bounds

            $timeslotStartTime = $scheduleStartTime + $timeslot * 60 * 60;
            $timeslotEndTime = $timeslotStartTime + 60 * 60;
            $timeslotType = null;

            $timeslotShiftStartOffset = 0;
            $timeslotShiftEndOffset = 0;
            $timeslotShift = null;

            switch ($activity) {
                case 'x':
                case 'X':  // unavailable
                    $timeslotType = ScheduleDatabase::STATE_UNAVAILABLE;
                    break;

                case '':  // available
                    $timeslotType = ScheduleDatabase::STATE_AVAILABLE;
                    break;

                default:  // shift
                    $timeslotType = ScheduleDatabase::STATE_SHIFT;
                    if (str_starts_with($activity, '#'))
                        $timeslotShiftStartOffset = 30 * 60;
                    if (str_ends_with($activity, '#'))
                        $timeslotShiftEndOffset = 30 * 60;

                    $timeslotShift = trim($activity, '#');
                    break;
            }

            // (1) No shift has been created yet, i.e. this is the volunteer's first shift. The
            //     key edge case here is when their first shift starts 30 minutes in. When that
            //     happens, we mark them as unavailable for the first 30 minutes.
            if (!$currentShift) {
                if ($timeslotShiftStartOffset > 0) {
                    $schedule[] = [
                        'type'      => ScheduleDatabase::STATE_UNAVAILABLE,
                        'start'     => $timeslotStartTime,
                        'end'       => $timeslotStartTime + $timeslotShiftStartOffset,
                    ];
                }

                if ($timeslotShiftEndOffset === 0) {
                    $currentShift = [
                        'type'      => $timeslotType,
                        'start'     => $timeslotStartTime + $timeslotShiftStartOffset,
                        'end'       => -1,
                    ];

                    if ($timeslotType === ScheduleDatabase::STATE_SHIFT)
                        $currentShift['shift'] = $timeslotShift;

                } else {
                    $currentShift = [
                        'type'      => $timeslotType,
                        'start'     => $timeslotStartTime + $timeslotShiftStartOffset,
                        'end'       => $timeslotEndTime - $timeslotShiftEndOffset,
                    ];

                    if ($timeslotType === ScheduleDatabase::STATE_SHIFT)
                        $currentShift['shift'] = $timeslotShift;

                    $schedule[] = $currentShift;

                    $currentShift = [
                        'type'      => ScheduleDatabase::STATE_AVAILABILITY_UNKNOWN,
                        'start'     => $timeslotEndTime - $timeslotShiftEndOffset,
                        'end'       => -1,
                    ];
                }

            // (2) The volunteer already is on an active shift. We need to identify whether they
            //     carry on doing exactly that during this timeslot, or whether they're doing
            //     something different. Key edge case is that we may have to generate an
            //     (UN)AVAILABLE shift if their previous shift ended early.
            } else {
                // (a) If the |$currentShift| has the STATE_AVAILABILITY_UNKNOWN state, we now have
                //     enough information to know what state it should be assigned it.
                if ($currentShift['type'] === ScheduleDatabase::STATE_AVAILABILITY_UNKNOWN) {
                    switch ($timeslotType) {
                        case ScheduleDatabase::STATE_UNAVAILABLE:
                        case ScheduleDatabase::STATE_AVAILABLE:
                            $currentShift['type'] = $timeslotType;
                            break;

                        case ScheduleDatabase::STATE_SHIFT:
                            $currentShift['type'] = ScheduleDatabase::STATE_AVAILABLE;
                            break;
                    }
                }

                // (b) Carry over the current shift if the activity is not changing, and will
                //     continue for this entire timeslot (i.e. doesn't finish halfway).
                if ($currentShift['type'] === $timeslotType) {
                    if ($currentShift['type'] !== ScheduleDatabase::STATE_SHIFT) {
                        continue;  // (un)available, carry-over
                    } else if ($currentShift['shift'] === $timeslotShift &&
                            $timeslotShiftStartOffset === 0 && $timeslotShiftEndOffset === 0) {
                        continue;  // same shift, full hour, carry-over
                    }
                }

                // (c) The shifts are changing, and we need to create a new one. Start by committing
                //     the |$currentShift| to the volunteer's |$schedule|. The logic for continuing
                //     (un)availability blocks is reversed from logic needed for shift blocks.
                $currentShift['end'] = $timeslotStartTime;

                $upcomingShiftStart = $timeslotStartTime + $timeslotShiftStartOffset;
                $upcomingShiftType = $timeslotType;

                switch ($currentShift['type']) {
                    case ScheduleDatabase::STATE_UNAVAILABLE:
                    case ScheduleDatabase::STATE_AVAILABLE:
                        if ($timeslotShiftStartOffset > 0)
                            $currentShift['end'] = $timeslotStartTime + $timeslotShiftStartOffset;

                        break;

                    case ScheduleDatabase::STATE_SHIFT:
                        if ($currentShift['shift'] === $timeslotShift) {
                            if ($timeslotShiftStartOffset > 0)
                                break;  // this volunteer is having an in-shift break

                            if ($timeslotShiftEndOffset > 0) {
                                $currentShift['end'] = $timeslotEndTime - $timeslotShiftEndOffset;
                            }

                            $upcomingShiftStart = $timeslotEndTime - $timeslotShiftEndOffset;
                            $upcomingShiftType = ScheduleDatabase::STATE_AVAILABILITY_UNKNOWN;
                        }

                        break;
                }

                $schedule[] = $currentShift;

                // (d) Create the new shift entry that the volunteer is engaging in. The logic is
                //     dependent on the type of |$upcomingShiftType|, if it ends before the current
                //     timeslot has finished then we may have to add an (un)availability block.
                switch ($upcomingShiftType) {
                    case ScheduleDatabase::STATE_AVAILABILITY_UNKNOWN:
                    case ScheduleDatabase::STATE_UNAVAILABLE:
                    case ScheduleDatabase::STATE_AVAILABLE:
                        $currentShift = [
                            'type'      => $upcomingShiftType,
                            'start'     => $upcomingShiftStart,
                            'end'       => -1,
                        ];

                        break;

                    case ScheduleDatabase::STATE_SHIFT:
                        if ($timeslotShiftStartOffset > 0 &&
                                $currentShift['type'] === ScheduleDatabase::STATE_SHIFT) {
                            $schedule[] = [
                                'type'      => ScheduleDatabase::STATE_AVAILABLE,
                                'start'     => $timeslotStartTime,
                                'end'       => $timeslotStartTime + $timeslotShiftStartOffset,
                            ];
                        }

                        if ($timeslotShiftEndOffset > 0) {
                            $schedule[] = [
                                'type'      => $upcomingShiftType,
                                'start'     => $upcomingShiftStart,
                                'end'       => $timeslotEndTime - $timeslotShiftEndOffset,
                                'shift'     => $timeslotShift,
                            ];

                            $currentShift = [
                                'type'      => ScheduleDatabase::STATE_AVAILABILITY_UNKNOWN,
                                'start'     => $timeslotEndTime - $timeslotShiftEndOffset,
                                'end'       => -1,
                            ];

                        } else {
                            $currentShift = [
                                'type'      => $upcomingShiftType,
                                'start'     => $upcomingShiftStart,
                                'end'       => -1,
                                'shift'     => $timeslotShift,
                            ];
                        }

                        break;
                }
            }
        }

        // Finally, add the |$currentShift| to the schedule, as the programme has finished.
        if ($currentShift) {
            if ($currentShift['type'] === ScheduleDatabase::STATE_AVAILABILITY_UNKNOWN)
                $currentShift['type'] = ScheduleDatabase::STATE_UNAVAILABLE;

            $currentShift['end'] = $timeslotEndTime;
            $schedule[] = $currentShift;
        }

        return $schedule;
    }
}
