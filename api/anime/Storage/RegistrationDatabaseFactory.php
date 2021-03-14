<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Storage;

use Anime\Cache;
use Anime\Storage\Backend\GoogleClient;
use Anime\Storage\Backend\GoogleSpreadsheet;

// The factory helps create a RegistrationDatabase instance by providing the necessary dependencies
// and caching mechanics. The database can either be opened in read-only mode or in read-write mode,
// where the difference is whether cached data will be used.
//
// Note that opening the database in read-only mode is no guarantee that no API calls will be issued
// as this depends on the existence of a recent cache, which may not be available. We don't allow
// transitioning between the data types as there is a risk of data loss when issuing writes based
// on cached data, which may not reflect the most recent state anymore.
class RegistrationDatabaseFactory {
    // Opens the database stored in |$spreadsheetId| in read-only mode.
    public static function openReadOnly(Cache $cache, string $spreadsheetId, string $sheetId) {
        $spreadsheet = new GoogleSpreadsheet(new GoogleClient(), $cache, $spreadsheetId);
        $sheet = $spreadsheet->getSheet($sheetId, /* writable= */ false);

        return new RegistrationDatabase($sheet);
    }

    // Opens the database stored in |$spreadsheetId| in read-write mode.
    public static function openReadWrite(Cache $cache, string $spreadsheetId, string $sheetId) {
        $spreadsheet = new GoogleSpreadsheet(new GoogleClient(), $cache, $spreadsheetId);
        $sheet = $spreadsheet->getSheet($sheetId, /* writable= */ true);

        return new RegistrationDatabase($sheet);
    }
}
