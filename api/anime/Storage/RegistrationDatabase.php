<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

use Anime\Storage\Backend\GoogleClient;
use Anime\Storage\Backend\GoogleSheet;
use Anime\Storage\Backend\GoogleSpreadsheet;
use Anime\Storage\Model\Registration;

// Provides access to the volunteer registrations. These are unique per environment, but shared
// across events given that there is a possibility of having multiple events per year. The database
// may be opened in a writable mode, in which case live data from the Google Sheets API is used.
class RegistrationDatabase {
    private ?array $events = null;
    private ?array $registrations = null;
    private GoogleSheet $sheet;
    private GoogleSpreadsheet $spreadsheet;

    public function __construct(
            GoogleClient $client, bool $writable, string $spreadsheetId, string $sheet) {
        $this->spreadsheet = new GoogleSpreadsheet($client, $spreadsheetId);
        $this->sheet = $this->spreadsheet->getSheet($sheet, $writable);
    }

    // ---------------------------------------------------------------------------------------------
    // Registration Database API
    // ---------------------------------------------------------------------------------------------

    // Returns an array with all registrations known to this database. Each registration will be
    // represented as an instance of the Registration model class.
    public function getRegistrations(): array {
        if (!$this->registrations)
            $this->initializeDatabase();

        return $this->registrations;
    }

    // ---------------------------------------------------------------------------------------------
    // Internal functionality
    // ---------------------------------------------------------------------------------------------

    // Initializes the registration data for first use. This only has to be called once, and will
    // ensure that the database's internal state is aligned with the data source.
    private function initializeDatabase(): void {
        $registrationData = $this->sheet->getRange('A1:ZZ999');
        if (!is_array($registrationData) || !count($registrationData))
            throw new \Exception('Unable to read data from the registration database.');

        $columns = $registrationData[0];
        if (!is_array($columns) || count($columns) <= Registration::DATA_COLUMN_COUNT)
            throw new \Exception('Invalid column data read from the registration database.');

        $this->events = array_slice($columns, Registration::DATA_COLUMN_COUNT);
        $this->registrations = [];

        for ($rowIndex = 1; $rowIndex < count($registrationData); ++$rowIndex) {
            if (count($registrationData[$rowIndex]) < Registration::DATA_COLUMN_COUNT)
                continue;  // ignore rows with invalid data

            $this->registrations[] = new Registration($registrationData[$rowIndex], $this->events);
        }
    }
}
