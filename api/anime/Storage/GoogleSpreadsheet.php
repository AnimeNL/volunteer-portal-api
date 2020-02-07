<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// The GoogleSpreadsheet class encapsulates programmatic access to a Google Sheet Spreadsheet. It
// requires a GoogleClient instance for authentication, and a spreadsheet ID.
class GoogleSpreadsheet {
    private $service;
    private $spreadsheetId;

    private $sheets = [];

    public function __construct(GoogleClient $googleClient, string $spreadsheetId) {
        $this->service = new \Google_Service_Sheets($googleClient->getClient());
        $this->spreadsheetId = $spreadsheetId;
    }

    // Returns a GoogleSheet instance for the |$sheet| in this particular spreadsheet. Safe to call
    // multiple times, repeated sheets will be cached.
    public function getSheet(string $sheet) : GoogleSheet {
        if (!array_key_exists($sheet, $this->sheets))
            $this->sheets[$sheet] = new GoogleSheet($this->service, $this->spreadsheetId, $sheet);
        
        return $this->sheets[$sheet];
    }
}