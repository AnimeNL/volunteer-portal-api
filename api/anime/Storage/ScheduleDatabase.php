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
    private GoogleSheet $mappingSheet;
    private GoogleSheet $scheduleSheet;
    private string $scheduleSheetStartDate;

    private ?array $mapping = null;

    public function __construct(
            GoogleSheet $mappingSheet, GoogleSheet $scheduleSheet, string $scheduleSheetStartDate) {
        $this->mappingSheet = $mappingSheet;
        $this->scheduleSheet = $scheduleSheet;
        $this->scheduleSheetStartDate = $scheduleSheetStartDate;
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

    // ---------------------------------------------------------------------------------------------
    // Internal functionality
    // ---------------------------------------------------------------------------------------------

    // Initializes the schedule database if needed. Will be a no-op when already initialized.
    private function initializeIfNeeded(): void {
        if (!$this->mapping)
            $this->initializeMapping();
    }

    // Initializes the event mapping contained within the mapping sheet. It's a simple sheet that
    // contains a list of shift identifiers, to the shift's name, event ID and/or location ID.
    private function initializeMapping(): void {
        $mappingData = $this->mappingSheet->getRange('A3:F100');
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
}
