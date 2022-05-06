<?php
// Copyright 2022 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Storage;

use \Exception;

class NotesDatabaseTest extends \PHPUnit\Framework\TestCase {
    // Verifies that the database can be opened from disk.
    public function testCreateDatabase() {
        try {
            $database = NotesDatabase::create('my-event');
            $this->assertTrue(true);
        } catch (Exception $error) {
            $this->fail('Unable to create the notes database');
        }
    }

    // Verifies that the basic read and write operations for the database work as expected.
    public function testBasicReadWrite() {
        $database = NotesDatabase::create('my-event');

        try {
            $database->delete('volunteer', 'joe');
            $database->delete('volunteer', 'rick');
            $database->delete('volunteer', 'max');  // <-- never exists
        } catch (Exception $error) {
            $this->fail('Unable to delete arbitrary values from the database');
        }

        $this->assertNull($database->get('volunteer', 'joe'));
        $this->assertNull($database->get('volunteer', 'rick'));

        $database->set('volunteer', 'joe', 'Hello!');
        $database->set('volunteer', 'rick', 'Bye!');

        $this->assertEquals($database->get('volunteer', 'joe'), 'Hello!');
        $this->assertEquals($database->get('volunteer', 'rick'), 'Bye!');

        $database->delete('volunteer', 'rick');

        $this->assertEquals($database->get('volunteer', 'joe'), 'Hello!');
        $this->assertNull($database->get('volunteer', 'rick'));

        $database->delete('volunteer', 'joe');
    }

    // Verifies that multiple events are isolated from each other.
    public function testEventIsolation() {
        $database2022 = NotesDatabase::create('my-event-2022');
        $database2022->set('volunteer', 'joe', '2022!');

        $database2023 = NotesDatabase::create('my-event-2023');
        $database2023->set('volunteer', 'joe', '2023!');

        $this->assertEquals($database2022->get('volunteer', 'joe'), '2022!');
        $this->assertEquals($database2023->get('volunteer', 'joe'), '2023!');

        $database2022->delete('volunteer', 'joe');
        $database2023->delete('volunteer', 'joe');

        $this->assertNull($database2022->get('volunteer', 'joe'));
        $this->assertNull($database2023->get('volunteer', 'joe'));
    }

    // Verifies that the specialised per-event read on the database works as expected.
    public function testEventRead() {
        $database = NotesDatabase::create('my-event');
        $database->set('event', '12345', 'title');
        $database->set('event', '67890', 'notes');

        $database->set('volunteer', 'joe', 'Hello!');
        $database->set('volunteer', 'rick', 'Bye!');

        $this->assertEqualsCanonicalizing(
            $database->all(),
            [
                'event' => [
                    '12345'     => 'title',
                    '67890'     => 'notes',
                ],
                'volunteer' => [
                    'joe'       => 'Hello!',
                    'rick'      => 'Bye!',
                ],
            ]);
    }
}
