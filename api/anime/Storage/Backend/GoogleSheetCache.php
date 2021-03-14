<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage\Backend;

// Cached version of the GoogleSheet that only allows read operations. May still connect with the
// Google Sheet API in case the stored cache does not exist, and has to be established.
class GoogleSheetCache extends GoogleSheet {
    // Maximum value to consider for the column in a spreadsheet, as a string.
    private const MAXIMUM_COLUMN = 'ZZ';

    // Maximum value to consider for the row in a spreadsheet, as a string.
    private const MAXIMUM_ROW = '999';

    // Maximum cell to consider in the spreadsheet. Aggregates the two aforementioned values.
    private const MAXIMUM_CELL = self::MAXIMUM_COLUMN . self::MAXIMUM_ROW;

    // ---------------------------------------------------------------------------------------------

    protected function get(string $range): ?array {
        $cacheKey = $this->getCacheKey();
        $cacheItem = $this->cache->getItem($cacheKey);

        // If the cache is not available yet, fetch the full sheet contents and store it in the
        // cache item. We will retrieve the intended sub-sections as a second step. We naively
        // assume that all data is contained in <=676 columns and <=999 rows.
        if (!$cacheItem->isHit())
            $this->cache->save($cacheItem->set(parent::get('A1:' . self::MAXIMUM_CELL)));

        $spreadsheet = $cacheItem->get();

        // Identify the exact properties of the |$range| of values that the caller wants to obtain.
        // This can be "A1" for a cell, "A:A" for a column, "A1:1" for a row or "A2:B4" for a range.
        if (str_contains($range, ':')) {
            $start = self::determineGridPosition(substr($range, 0, strpos($range, ':')));
            $end = self::determineGridPosition(substr($range, strpos($range, ':') + 1), $start);

            if ($start[0] > $end[0] || $start[1] > $end[1])
                throw new \Exception('Invalid (reversed) range provided: ' . $range);

            $selectedRows = [];
            if ($start[1] >= 0 && $start[1] < count($spreadsheet)) {  // validate row
                if ($start[0] >= 0 && $start[0] < count($spreadsheet[0])) {  // validate column
                    $selectedRows = array_slice($spreadsheet, $start[1], 1 + ($end[1] - $start[1]));
                    foreach ($selectedRows as &$row)
                        $row = array_slice($row, $start[0], 1 + ($end[0] - $start[0]));
                }
            }

            return $selectedRows;

        } else {
            $cell = self::determineGridPosition($range);
            if ($cell[0] >= 0 && $cell[0] < count($spreadsheet)) {
                if ($cell[1] >= 0 && $cell[1] < count($spreadsheet[$cell[0]]))
                    return [ [ $spreadsheet[$cell[0]][$cell[1]] ] ];
            }
        }

        return [];
    }

    protected function write(string $range, array $values, string $direction): bool {
        throw new \Exception('The Google Sheet has been opened in read-only mode.');
    }

    protected function clear(string $range): bool {
        throw new \Exception('The Google Sheet has been opened in read-only mode.');
    }

    // ---------------------------------------------------------------------------------------------

    // Returns whether the sheet has been opened in writable mode.
    public function writable(): bool { return false; }

    // ---------------------------------------------------------------------------------------------

    // Returns the grid position of the given |$cell| as an array containing [x, y], zero-based. The
    // |$referenceCell| may be given if the |$cell| could be determined in relation to another.
    // This method should be considered private, but is visible for testing purposes.
    static function determineGridPosition(string $cell, ?array $referenceCell = null): array {
        if (!preg_match('/^([A-Z]*)([0-9]*)$/s', $cell, $matches))
            throw new \Exception('Unable to parse the given cell parameter: ' . $cell);

        $column = $matches[1];
        $row = $matches[2];

        // If the |$column| and |$row| both are empty, the cell is invalid. If the |$column| has
        // been omitted, consider the full row. If the |$row| has been omitted, consider the column.
        if (!strlen($column) && !strlen($row))
            throw new \Exception('Unexpected matches for the given cell parameter: ' . $cell);
        else if (!strlen($column))
            $column = $referenceCell ? self::MAXIMUM_COLUMN : 'A';
        else if (!strlen($row))
            $row = $referenceCell ? self::MAXIMUM_ROW : '1';

        return [
            self::columnToIndex($column),
            self::rowToIndex($row)
        ];
    }

    // Returns the index associated with the given |$column|, which is in "AA"-like Sheet syntax.
    private static function columnToIndex(string $column): int {
        $normalizedColumn = strtoupper($column);
        $index = 0;

        for ($character = 0; $character < strlen($normalizedColumn); ++$character)
            $index = $index * 26 + (ord($normalizedColumn[$character]) - 0x40);

        return $index - 1;
    }

    // Returns the index associated with the given |$row|, which is one-indexed.
    private static function rowToIndex(string $row): int {
        return intval($row, /* base= */ 10) - 1;
    }
}
