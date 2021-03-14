<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage\Backend;

use Anime\Cache;
use \Google_Service_Sheets;

// The GoogleSpreadsheet class encapsulates programmatic access to a Google Sheet Spreadsheet. It
// requires a GoogleClient instance for authentication, and a spreadsheet ID.
class GoogleSpreadsheet {
    private Cache $cache;
    private Google_Service_Sheets $service;
    private string $spreadsheetId;

    private array $sheets = [];

    public function __construct(GoogleClient $googleClient, Cache $cache, string $spreadsheetId) {
        $this->cache = $cache;
        $this->service = new Google_Service_Sheets($googleClient->getClient());
        $this->spreadsheetId = $spreadsheetId;
    }

    // Returns a GoogleSheet instance for the |$sheet| in this particular spreadsheet. |$writable|
    // indicates whether the sheet can be written to, influencing whether the data will be cached.
    // Safe to call multiple times, repeated sheets will be cached.
    public function getSheet(string $sheet, bool $writable = true): GoogleSheet {
        if (!array_key_exists($sheet, $this->sheets)) {
            if ($writable) {
                $this->sheets[$sheet] = new GoogleSheet(
                    $this->cache, $this->service, $this->spreadsheetId, $sheet);
            } else {
                $this->sheets[$sheet] = new GoogleSheetCache(
                    $this->cache, $this->service, $this->spreadsheetId, $sheet);
            }
        }

        if ($this->sheets[$sheet]->writable() !== $writable)
            throw new \Exception('An instance already exists with incompatible mutability.');

        return $this->sheets[$sheet];
    }
}
