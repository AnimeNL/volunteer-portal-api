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

    public function __construct(GoogleSheet $mappingSheet, GoogleSheet $scheduleSheet) {
        $this->mappingSheet = $mappingSheet;
        $this->scheduleSheet = $scheduleSheet;
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

    // ..

    // ---------------------------------------------------------------------------------------------
    // Internal functionality
    // ---------------------------------------------------------------------------------------------

    // ..
}
