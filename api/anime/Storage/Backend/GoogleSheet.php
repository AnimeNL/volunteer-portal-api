<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage\Backend;

use \Anime\Cache;
use \Google_Service_Sheets;

const DIRECTION_ROW = 'ROWS';
const DIRECTION_COLUMN = 'COLUMNS';

const VALUE_INPUT_OPTION_RAW = 'RAW';
const VALUE_INPUT_OPTION_USER_ENTERED = 'USER_ENTERED';

// The GoogleSheet class encapsulates programmatic access to a particular sheet in a Google
// Spreadsheet document. Should only be created by and obtained through GoogleSpreadsheet. This
// class operates on the assumption that interaction is read-write, but a specialized class is
// available for enabling cache-driven read-only interactions.
class GoogleSheet {
    protected Cache $cache;
    private Google_Service_Sheets $service;
    private string $spreadsheetId;
    private string $sheet;

    public function __construct(Cache $cache, Google_Service_Sheets $service, string $spreadsheetId, string $sheet) {
        $this->cache = $cache;
        $this->service = $service;
        $this->spreadsheetId = $spreadsheetId;
        $this->sheet = $sheet;
    }

    // ---------------------------------------------------------------------------------------------

    // Returns contents of the given |$cell|, which must be in the format of "A2".
    public function getCell(string $cell): ?string {
        $this->assertValidCell($cell);

        $values = $this->get($cell);

        if (count($values) == 1 && count($values[0]) == 1)
            return $values[0][0];

        return null;
    }

    // Returns the contents of the given |$column|, which must be in the format of "A".
    public function getColumn(string $column): ?array {
        $this->assertValidColumn($column);
        
        $values = $this->get($column . ':' . $column);

        if (count($values) >= 1) {
            return array_map(function ($entry) {
                return count($entry) == 1 ? $entry[0]
                                          : null;
            }, $values);
        }

        return null;
    }

    // Returns the contents of the given |$range|, which must be in the format of "A2:C20".
    public function getRange(string $range): ?array {
        $this->assertValidRange($range);
        
        $values = $this->get($range);
        
        if (count($values) >= 1)
            return $values;
        
        return null;
    }

    // Returns the contents of the given |$row|, which must be a valid number.
    public function getRow(int $row): ?array {
        $values = $this->get('A' . $row . ':' . $row);

        if (count($values) == 1)
            return $values[0];

        return null;
    }

    // ---------------------------------------------------------------------------------------------

    // Clears the given |$cell|, which must be in the format of "A1".
    public function clearCell(string $cell): bool {
        $this->assertValidCell($cell);
        return $this->clear($cell);
    }

    // Clears the given |$column|, which must be in the format of "A".
    public function clearColumn(string $column): bool {
        $this->assertValidColumn($column);
        return $this->clear($column . ':' . $column);

    }

    // Clears the given |$range|, which must be in the format of "A1:E2".
    public function clearRange(string $range): bool {
        $this->assertValidRange($range);
        return $this->clear($range);

    }

    // Clears the given |$row|, which must be a valid number.
    public function clearRow(int $row): bool {
        return $this->clear('A' . $row . ':' . $row);
    }

    // ---------------------------------------------------------------------------------------------

    // Writes the given |$value| to the given |$cell|.
    public function writeCell(string $cell, string $value): bool {
        $this->assertValidCell($cell);
        
        return $this->write($cell, [ [ $value ] ], DIRECTION_ROW);
    }

    // Writes the given |$values| to the given |$cell| as a 2-dimensional matrix. NULL values may be
    // used to indicate that a cell should be skipped when processing the update.
    public function writeCells(string $cell, array $values): bool {
        $this->assertValidCell($cell);
        $this->assertValidValueMatrix($values);

        return $this->write($cell, $values, DIRECTION_ROW);
    }

    // Writes the given |$values| to the given |$cell| as a column. NULL values may be used to
    // indicate that a cell should be skipped when processing the update.
    public function writeColumn(string $cell, array $values): bool {
        $this->assertValidCell($cell);
        $this->assertValidSingleDimensionValues($values);

        return $this->write($cell, [ $values ], DIRECTION_COLUMN);
    }

    // Writes the given |$values| to the given |$cell| as a row. NULL values may be used to indicate
    // that a cell should be skipped when processing the update.
    public function writeRow(string $cell, array $values): bool {
        $this->assertValidCell($cell);
        $this->assertValidSingleDimensionValues($values);

        return $this->write($cell, [ $values ], DIRECTION_ROW);
    }

    // ---------------------------------------------------------------------------------------------

    // Internal function that actually retrieves the information from the spreadsheet sheet. Returns
    // an array with the contents of the given cells.
    protected function get(string $range): ?array {
        $localRange = $this->sheet . '!' . $range;
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $localRange);

        return $response->getValues();
    }

    // Internal function that actually operates a write on the spreadsheet sheet. The cache will be
    // invalidated following a successful write.
    protected function write(string $range, array $values, string $direction): bool {
        $localRange = $this->sheet . '!' . $range;
        $request = new \Google_Service_Sheets_ValueRange([
            'majorDimension'    => $direction,
            'range'             => $localRange,
            'values'            => $this->replaceNullValues($values),
        ]);

        $parameters = [
            'valueInputOption' => VALUE_INPUT_OPTION_RAW,
        ];

        $response = $this->service->spreadsheets_values->update(
            $this->spreadsheetId, $localRange, $request, $parameters);

        $success = $response->updatedCells >= 1;
        if ($success)
            $this->cache->deleteItem($this->getCacheKey());
        
        return $success;
    }

    // Internal function that actually operates a clear on the spreadsheet sheet. The cache will be
    // invalidated following a successful clear, as it manipulates the data.
    protected function clear(string $range): bool {
        $localRange = $this->sheet . '!' . $range;

        $this->service->spreadsheets_values->clear(
            $this->spreadsheetId, $localRange, new \Google_Service_Sheets_ClearValuesRequest());

        $this->cache->deleteItem($this->getCacheKey());
        return true;
    }

    // ---------------------------------------------------------------------------------------------

    // Returns the cache key under which data for this sheet has been cached, if at all. This must
    // be a valid key per the PSR-6 definition, which is rather restrictive. Should be considered as
    // a private method, but not marked as such to enable testing.
    public function getCacheKey(): string {
        return 'GS.' . sha1($this->spreadsheetId . $this->sheet);
    }

    // Returns whether the sheet has been opened in writable mode.
    public function writable(): bool { return true; }

    // ---------------------------------------------------------------------------------------------

    // Validates whether the given |$cell| is a valid cell, or throws an exception otherwise.
    private function assertValidCell(string $cell) {
        if (!preg_match('/^[A-Z]{1,3}[0-9]{1,3}$/s', $cell))
            throw new \Exception('Invalid cell given: ' . $cell);
    }

    // Validates whether the given |$column| is a valid column, or throws an exception otherwise.
    private function assertValidColumn(string $column) {
        if (!preg_match('/^[A-Z]{1,3}$/s', $column))
            throw new \Exception('Invalid column given: ' . $column);
    }

    // Validates whether the given |$range| is a valid range, or throws an exception otherwise.
    private function assertValidRange(string $range) {
        if (!preg_match('/^[A-Z]{1,3}[0-9]{1,3}:[A-Z]{1,3}[0-9]{1,3}$/s', $range))
            throw new \Exception('Invalid range given: ' . $range);
    }

    // Validates whether the given |$value| is valid to be written to a single cell.
    private function validateSingleValue($value): bool {
        return is_string($value) || is_int($value) || is_null($value);
    }

    // Validates whether the given |$values| are valid to be written in single-dimension format.
    private function assertValidSingleDimensionValues(array $values) {
        foreach ($values as $key => $value) {
            if (!$this->validateSingleValue($value))
                throw new \Exception('Invalid value given: ' . $value);
        }
    }

    // Validates whether the given |$values| are a valid two-dimensional value matrix.
    private function assertValidValueMatrix(array $values) {
        foreach ($values as $key => $value) {
            if (!is_array($value))
                throw new \Exception('Single dimensional data received.');
            
            $this->assertValidSingleDimensionValues($value);
        }
    }

    // Recursively replaces null values in |$values| with the magic value in \Google_Model.
    private function replaceNullValues(array $values) {
        foreach ($values as $key => $value) {
            if (is_array($value))
                $values[$key] = $this->replaceNullValues($value);
            if (is_null($value))
                $values[$key] = \Google_Model::NULL_VALUE;
        }

        return $values;
    }
}
