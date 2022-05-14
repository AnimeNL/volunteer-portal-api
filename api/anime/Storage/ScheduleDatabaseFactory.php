<?php
// Copyright 2022 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Storage;

use Anime\Cache;
use Anime\Storage\Backend\GoogleClient;
use Anime\Storage\Backend\GoogleSpreadsheet;

// The factory helps create a ScheduleDatabase instance by providing the necessary dependencies
// and caching mechanics. The database can either be opened in read-only mode or in read-write mode,
// where the difference is whether cached data will be used.
class ScheduleDatabaseFactory {
    // Opens the database stored in |$spreadsheetId| in read-only mode.
    public static function openReadOnly(
            Cache $cache, string $spreadsheetId, string $mappingSheetId, string $scheduleSheetId,
            string $scheduleSheetStartDate) {
        $spreadsheet = new GoogleSpreadsheet(new GoogleClient(), $cache, $spreadsheetId);

        $mappingSheet = $spreadsheet->getSheet($mappingSheetId, /* writable= */ false);
        $scheduleSheet = $spreadsheet->getSheet($scheduleSheetId, /* writable= */ false);

        return new ScheduleDatabase($mappingSheet, $scheduleSheet, $scheduleSheetStartDate);
    }
}
