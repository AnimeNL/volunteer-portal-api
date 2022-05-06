<?php
// Copyright 2022 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

use Anime\Cache;
use \SQLite3;
use \SQLite3Stmt;

// The notes database is able to store notes for a variety of entities, each keyed by a particular
// event instance. The backend is provided by a simple on-disk SQLite database.
class NotesDatabase {
    // Path to the on-disk SQLite database used for notes.
    public const DATABASE_PATH = Cache::CACHE_PATH . '/NotesDatabase.sqlite3';

    // Creates a NotesDatabase instance for the given |$event|. Multiple events are able to share
    // the same SQLite3 database, but operations are keyed to an individual event.
    public static function create(string $event): NotesDatabase {
        $database = new self($event);
        $database->ensureCreateTable();
        $database->prepareStatements();

        return $database;
    }

    private SQLite3 $database;
    private string $event;

    private SQLite3Stmt $allStatement;
    private SQLite3Stmt $getStatement;
    private SQLite3Stmt $setStatement;
    private SQLite3Stmt $deleteStatement;

    // Do not use the constructor directly, instead, call create()
    private function __construct(string $event) {
        $this->database = new SQLite3(self::DATABASE_PATH);
        $this->event = $event;
    }

    // Reads all notes for the current event from the database.
    public function all(): array {
        $this->allStatement->bindValue(':event', $this->event, SQLITE3_TEXT);

        $result = $this->allStatement->execute();
        $resultValue = [];

        if ($result !== false) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                if (!array_key_exists($row['entity_type'], $resultValue))
                    $resultValue[$row['entity_type']] = [];

                $resultValue[$row['entity_type']][$row['entity_id']] = $row['notes'];
            }

            $result->finalize();
        }

        $this->allStatement->reset();
        return $resultValue;
    }

    // Reads the notes associated with the given |$entityId| from the database.
    public function get(string $entityType, string $entityId): ?string {
        $this->getStatement->bindValue(':event', $this->event, SQLITE3_TEXT);
        $this->getStatement->bindValue(':entity_type', $entityType, SQLITE3_TEXT);
        $this->getStatement->bindValue(':entity_id', $entityId, SQLITE3_TEXT);

        $result = $this->getStatement->execute();
        $resultValue = null;

        if ($result !== false) {
            [ 'notes' => $resultValue ] = $result->fetchArray(SQLITE3_ASSOC);
            $result->finalize();
        }

        $this->getStatement->reset();
        return $resultValue;
    }

    // Writes the given |$notes| for the given |$entityId| to the database.
    public function set(string $entityType, string $entityId, string $notes): void {
        $this->setStatement->bindValue(':event', $this->event, SQLITE3_TEXT);
        $this->setStatement->bindValue(':entity_type', $entityType, SQLITE3_TEXT);
        $this->setStatement->bindValue(':entity_id', $entityId, SQLITE3_TEXT);
        $this->setStatement->bindValue(':notes', $notes, SQLITE3_TEXT);

        $this->setStatement->execute();
        $this->setStatement->reset();
    }

    // Deletes the notes associated with the given |$entityId| from the database.
    public function delete(string $entityType, string $entityId): void {
        $this->deleteStatement->bindValue(':event', $this->event, SQLITE3_TEXT);
        $this->deleteStatement->bindValue(':entity_type', $entityType, SQLITE3_TEXT);
        $this->deleteStatement->bindValue(':entity_id', $entityId, SQLITE3_TEXT);

        $this->deleteStatement->execute();
        $this->deleteStatement->reset();
    }

    // Initialize the appropriate table. Will not override the table if it already exists.
    private function ensureCreateTable(): void {
        $result = $this->database->querySingle(
            'SELECT name FROM sqlite_master WHERE type="table" AND name="notes"');

        if ($result === 'notes')
            return;  // the table exists

        $createTableResult = $this->database->query(
            'CREATE TABLE IF NOT EXISTS notes (' .
                'event TEXT NOT NULL, ' .
                'entity_type TEXT NOT NULL, ' .
                'entity_id TEXT NOT NULL, ' .
                'notes TEXT NOT NULL)'
        );

        if (!$createTableResult)
            throw new \Error('Unable to creates the `notes` database table.');

        $eventIndexResult = $this->database->query('CREATE INDEX idx_notes_event ON notes(event)');
        if (!$eventIndexResult)
            throw new \Error('Unable to creates the event index on the `notes` table.');

        $noteIndexResult = $this->database->query(
            'CREATE UNIQUE INDEX idx_notes_note ON notes(event, entity_type, entity_id)');

        if (!$noteIndexResult)
            throw new \Error('Unable to creates the note index on the `notes` table.');
    }

    // Initializes the prepared statements used for working with the table.
    private function prepareStatements(): void {
        $this->allStatement = $this->database->prepare('
            SELECT
                entity_type,
                entity_id,
                notes
            FROM
                notes
            WHERE
                event=:event');

        $this->getStatement = $this->database->prepare('
            SELECT
                notes
            FROM
                notes
            WHERE
                event=:event AND
                entity_type=:entity_type AND
                entity_id=:entity_id');

        $this->setStatement = $this->database->prepare('
            INSERT OR REPLACE INTO
                notes
                (event, entity_type, entity_id, notes)
            VALUES
                (:event, :entity_type, :entity_id, :notes)');

        $this->deleteStatement = $this->database->prepare('
            DELETE FROM
                notes
            WHERE
                event=:event AND
                entity_type=:entity_type AND
                entity_id=:entity_id');
    }
}
