<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage\Backend;

use \Anime\Cache;
use \Anime\Test\AssertException;

class GoogleSheetCacheTest extends \PHPUnit\Framework\TestCase {
    use AssertException;

    // Verifies that the functionality to convert cell descriptions to ranges works as expected.
    public function testGridPositionDetermination() {
        $this->assertEquals(GoogleSheetCache::determineGridPosition('A1'), [ 0, 0 ]);
        $this->assertEquals(GoogleSheetCache::determineGridPosition('B2'), [ 1, 1 ]);
        $this->assertEquals(GoogleSheetCache::determineGridPosition('AB999'), [ 27, 998 ]);
        $this->assertEquals(GoogleSheetCache::determineGridPosition('ZZ999'), [ 701, 998 ]);

        $this->assertEquals(GoogleSheetCache::determineGridPosition('A'), [ 0, 0 ]);
        $this->assertEquals(GoogleSheetCache::determineGridPosition('A', [ 0, 0 ]), [ 0, 998 ]);

        $this->assertEquals(GoogleSheetCache::determineGridPosition('1'), [ 0, 0 ]);
        $this->assertEquals(GoogleSheetCache::determineGridPosition('1', [ 0, 0 ]), [ 701, 0 ]);

        $this->assertEquals(GoogleSheetCache::indexToColumn(0), 'A');
        $this->assertEquals(GoogleSheetCache::indexToColumn(5), 'F');
        $this->assertEquals(GoogleSheetCache::indexToColumn(25), 'Z');
        $this->assertEquals(GoogleSheetCache::indexToColumn(26), 'AA');
        $this->assertEquals(GoogleSheetCache::indexToColumn(701), 'ZZ');
        $this->assertEquals(GoogleSheetCache::indexToColumn(702), 'AAA');
    }

    // Verifies that selection of particular items in the grid continues to work, even for cached
    // sheets in which case we manage the selection ourselves.
    public function testEmulatedGridSelection() {
        $cache = Cache::getInstance();
        $client = new GoogleClient();  // no requests should be issued

        $spreadsheet = new GoogleSpreadsheet($client, $cache, '_test_spreadsheet_id_');
        $sheet = $spreadsheet->getSheet('_test_sheet_id_', /* writable= */ false);

        // (1) Build the fake spreadsheet in the |$cache|. The test creates a 4x4 matrix in which
        //     each cell is filled with a character of the alphabet.
        {
            $cacheItem = $cache->getItem($sheet->getCacheKey());
            $cacheItem->set([
                [
                    'A',  // A1
                    'B',  // B1
                    'C',  // C1
                    'D',  // D1
                ],
                [
                    'E',  // A2
                    'F',  // B2
                    'G',  // C2
                    'H',  // D2
                ],
                [
                    'I',  // A3
                    'J',  // B3
                    'K',  // C3
                    'L',  // D3
                ],
                [
                    'M',  // A4
                    'N',  // B4
                    'O',  // C4
                    'P',  // D4
                ],
            ]);

            $cache->save($cacheItem);
        }

        // (2) Verify that different operations in |$sheet| return the right result.
        // (2a) Cells.
        {
            $this->assertEquals('A', $sheet->getCell('A1'));
            $this->assertEquals('F', $sheet->getCell('B2'));
            $this->assertEquals('K', $sheet->getCell('C3'));
            $this->assertEquals('P', $sheet->getCell('D4'));

            $this->assertNull($sheet->getCell('A5'));
            $this->assertNull($sheet->getCell('E1'));
            $this->assertNull($sheet->getCell('E5'));
        }

        // (2b) Columns.
        {
            $this->assertEquals([ 'A', 'E', 'I', 'M' ], $sheet->getColumn('A'));
            $this->assertEquals([ 'D', 'H', 'L', 'P' ], $sheet->getColumn('D'));
            $this->assertNull($sheet->getColumn('E'));
        }

        // (2c) Rows.
        {
            $this->assertEquals([ 'A', 'B', 'C', 'D' ], $sheet->getRow(1));
            $this->assertEquals([ 'M', 'N', 'O', 'P' ], $sheet->getRow(4));
            $this->assertNull($sheet->getRow(0));
            $this->assertNull($sheet->getRow(5));
        }

        // (2d) Ranges.
        {
            $this->assertEquals([ [ 'F', 'G' ] ], $sheet->getRange('B2:C2'));
            $this->assertEquals([ [ 'E' ], [ 'I' ] ], $sheet->getRange('A2:A3'));
            $this->assertEquals(
                [ [ 'A', 'B' ], [ 'E', 'F' ], [ 'I', 'J' ] ], $sheet->getRange('A1:B3'));

            $this->assertEquals([ [ 'C', 'D' ] ], $sheet->getRange('C1:Z1'));
            $this->assertEquals([ [ 'I' ], [ 'M' ] ], $sheet->getRange('A3:A214'));
            $this->assertNull($sheet->getRange('A5:A6'));
            $this->assertNull($sheet->getRange('E1:E3'));
        }

        // (2e) Invalid ranges.
        {
            $this->assertException(function() use ($sheet) {
                $sheet->getRange('B2:A1');
            });

            $this->assertException(function() use ($sheet) {
                $sheet->getRange('A2:A1');
            });

            $this->assertException(function() use ($sheet) {
                $sheet->getRange('B1:A1');
            });
        }
    }

    // Verifies that an exception will be thrown when attempting to write to a Sheet instance that
    // has been opened in read-only mode, which is not considered to be valid.
    public function testAssertsMutability() {
        $cache = Cache::getInstance();
        $client = new GoogleClient();  // no requests should be issued

        $spreadsheet = new GoogleSpreadsheet($client, $cache, '_test_spreadsheet_id_');
        $sheet = $spreadsheet->getSheet('_test_sheet_id_', /* writable= */ false);

        $this->assertException(function() use ($sheet) {
            $sheet->writeCell('B1', 'Hello, world!');
        });

        $this->assertException(function() use ($sheet) {
            $sheet->clearCell('A1');
        });
    }
}
