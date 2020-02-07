<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// The volunteer database contains all information about a group of volunteers for the event.
//
// The database supports full read and write access. When opened in READ_ONLY mode, cached content
// will be used when available to speed up operations. When opened in READ_WRITE mode, the actual
// data source will be used which will cause additional RPCs, and thus latency.
//
// The database is backed by a Google Spreadsheet in a predefined format, documented well throughout
// the code in the table definition files, which act as some sort of ORM. Normally this would be
// considered a really bad practice, but since personel management is done through the spreadsheet
// as-is, this avoids a whole lot of duplication.
class VolunteerDatabase {
    // Operation modes using which the database can be opened.
    public const READ_ONLY = 0;
    public const READ_WRITE = 1;
    
    // The data source which provides data and operations for the current configuration.
    private $dataSource;

    public function __construct(string $googleSpreadsheetId, int $mode) {
        // TODO: Actually support a CachedDataSource if |$mode| allows.
        $this->dataSource = new GoogleDataSource($googleSpreadsheetId);
        
        print_r($this->dataSource->getRegistrations());
    }
}
