<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// Name of the sheet that contains the registration information.
const REGISTRATIONS_SHEET_NAME = 'Registrations';

// First row on which registrations exist on the registrations sheet.
const REGISTRATIONS_SHEET_ROW = 2;

// The GoogleDataSource implements the data source interface whilst being backed by the live Google
// Spreadsheet that's used for volunteer administration.
class GoogleDataSource implements VolunteerDataSource {
    private $spreadsheet;

    private $registrations = null;

    public function __construct(string $spreadsheetId) {
        // Create the backing GoogleSpreadsheet instance. An exception will be thrown if the client
        // could not be authenticated prior to usage. Run "auth.php" when that happens.
        $this->spreadsheet = new GoogleSpreadsheet(new GoogleClient(), $spreadsheetId);
    }

    // ---------------------------------------------------------------------------------------------
    // VolunteerDataSource implementation

    public function createRegistration(VolunteerRegistrationRequest $request) : void {
        $registrationRow = REGISTRATIONS_SHEET_ROW;

        // (1) Determine the row on which the registration should be inserted.
        foreach ($this->getRegistrations() as $registration)
            $registrationRow = max($registrationRow, 1 + $registration->getSpreadsheetRow());

        // (2) Create the new VolunteerRegistration instance for the |$request|.
        $newRegistration = VolunteerRegistrationFactory::FromRequest($request);
        $newRegistration->setSpreadsheetRow($registrationRow);

        // (3) Write the registration information back to the spreadsheet.
        {
            $spreadsheet = $this->spreadsheet->getSheet(REGISTRATIONS_SHEET_NAME);
            $spreadsheetRow = VolunteerRegistrationFactory::ToSpreadsheetRow($newRegistration);

            $spreadsheet->writeRow('A' . $registrationRow, $spreadsheetRow);
        }

        // (4) Store the new registration in the cached registrations.
        $this->registrations[] = $newRegistration;
    }

    public function getRegistrations() : array {
        if ($this->registrations === null) {
            $registrations = $this->spreadsheet->getSheet(REGISTRATIONS_SHEET_NAME);
            $registrationData = $registrations->getRange('A' . REGISTRATIONS_SHEET_ROW . ':L999');

            $this->registrations = [];

            foreach ($registrationData as $rowOffset => $registrationEntry) {
                $registration = VolunteerRegistrationFactory::FromSpreadsheetRow($registrationEntry);
                if (is_null($registration))
                    continue;

                $registration->setSpreadsheetRow($rowOffset + REGISTRATIONS_SHEET_ROW);

                $this->registrations[] = $registration;
            }
        }

        return $this->registrations;
    }
}
