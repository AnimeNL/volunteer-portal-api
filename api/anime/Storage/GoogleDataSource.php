<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// Name of the sheet that contains the registration information.
const REGISTRATIONS_SHEET_NAME = 'Registrations';

// The GoogleDataSource implements the data source interface whilst being backed by the live Google
// Spreadsheet that's used for volunteer administration.
class GoogleDataSource implements VolunteerDataSource {
    private $spreadsheet;

    private $registrationCache = null;

    public function __construct(string $spreadsheetId) {
        // Create the backing GoogleSpreadsheet instance. An exception will be thrown if the client
        // could not be authenticated prior to usage. Run "auth.php" when that happens.
        $this->spreadsheet = new GoogleSpreadsheet(new GoogleClient(), $spreadsheetId);
    }

    // ---------------------------------------------------------------------------------------------
    // VolunteerDataSource implementation

    public function getRegistrations() : array {
        if ($this->registrationCache === null) {
            $registrations = $this->spreadsheet->getSheet(REGISTRATIONS_SHEET_NAME);
            $registrationData = $registrations->getRange('A2:L999');

            $this->registrationCache = [];

            foreach ($registrationData as $registrationEntry) {
                if (!VolunteerRegistration::Validate($registrationEntry))
                    continue;
                
                $this->registrationCache[] = new VolunteerRegistration($registrationEntry);
            }
        }

        return $this->registrationCache;
    }
}
