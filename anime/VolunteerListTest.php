<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime;

class VolunteerListTest extends \PHPUnit\Framework\TestCase {
    use \Anime\Test\AssertException;

    // Verifies that the findByName() function can find volunteers by name.
    public function testFindVolunteer() {
        $list = VolunteerList::create([
            $this->createVolunteer('Peter'),
            $this->createVolunteer('Fërdi Servile'),
            $this->createVolunteer('Remy Eendenkwaak-de Oude')
        ]);

        $this->assertInstanceOf(VolunteerList::class, $list);

        $peter = $list[0];
        $ferdi = $list[1];
        $remy = $list[2];

        $this->assertNull($list->findByName('Nardo'));

        $this->assertEquals($peter, $list->findByName('Peter'));
        $this->assertEquals($peter, $list->findByName('peter', true /* fuzzy */));
        $this->assertNull($list->findByName('peter', false /* fuzzy */));

        $this->assertEquals($ferdi, $list->findByName('Fërdi Servile', false /* fuzzy */));
        $this->assertEquals($ferdi, $list->findByName('Fërdi Servile', true /* fuzzy */));
        $this->assertEquals($ferdi, $list->findByName('ferdi servile', true /* fuzzy */));
        $this->assertNull($list->findByName('ferdi servile', false /* fuzzy */));

        $this->assertEquals($remy, $list->findByName('Remy Eendenkwaak-de Oude'));
        $this->assertEquals($remy, $list->findByName('remy eendenkwaak de oude', true /* fuzzy */));
        $this->AssertEquals($remy, $list->findByName('REMYEENDENKWAAKDEOUDE', true /* fuzzy */));
    }

    // Verifies that the findByToken() method returns the expected volunteer.
    public function testFindToken() {
        $list = VolunteerList::create([ $this->createVolunteer('MyName') ]);

        $this->assertInstanceOf(VolunteerList::class, $list);
        $this->assertEquals($list[0], $list->findByToken($list[0]->getToken()));
    }

    // Verifies that the functionality of the ArrayAccess interface has been implemented correctly.
    public function testArrayAccessInterface() {
        $list = VolunteerList::create([
            $this->createVolunteer('Peter'),
            $this->createVolunteer('Ferdi'),
            $this->createVolunteer('Neil')
        ]);

        $this->assertInstanceOf(VolunteerList::class, $list);

        // offsetExists
        $this->assertTrue(isset($list[1]) /* Ferdi */);
        $this->assertFalse(isset($list[-1]));
        $this->assertFalse(isset($list[20]));

        // offsetGet
        $this->assertEquals('Peter', $list[0]->getName());
        $this->assertNull($list[-1]);
        $this->assertNull($list[20]);

        // offsetSet
        $this->assertException(function () use ($list) {
            $list[1] = $list[0];
        });

        // offsetUnset
        $this->assertException(function () use ($list) {
            unset($list[1]);
        });
    }

    // Verifies that the functionality of the Countable interface has been implemented correctly.
    public function testCountableInterface() {
        $list = VolunteerList::create([
            $this->createVolunteer('Peter'),
            $this->createVolunteer('Ferdi'),
            $this->createVolunteer('Neil')
        ]);

        $this->assertInstanceOf(VolunteerList::class, $list);

        // count
        $this->assertEquals(3, count($list));
    }

    // Verifies that the functionality of the IteratorAggregate interface has been implemented
    // correctly, i.e. that the VolunteerList can be iterated over.
    public function testIteratorAggregateInterface() {
        $list = VolunteerList::create([
            $this->createVolunteer('Peter'),
            $this->createVolunteer('Ferdi'),
            $this->createVolunteer('Neil')
        ]);

        $this->assertInstanceOf(VolunteerList::class, $list);

        $names = [];
        foreach ($list as $volunteer)
            $names[] = $volunteer->getName();

        $this->assertEquals(['Peter', 'Ferdi', 'Neil'], $names);
    }

    // Creates a volunteer object. |$name| is required, the |$options| array can contain any of
    // the optional properties that are being parsed in this method.
    private function createVolunteer(string $name, array $options = []) {
        return [
            'name'      => $name,
            'password'  => '',

            'type'      => array_key_exists('type', $options) ? $options['type'] : 'Volunteer',
            'title'     => array_key_exists('title', $options) ? $options['title'] : null,

            'email'     => array_key_exists('email', $options) ? $options['email']
                                                               : 'info@example.com',
            'telephone' => array_key_exists('telephone', $options) ? $options['telephone']
                                                                   : '+31 (0)6 123 45 678',

            'visible'   => array_key_exists('visible', $options) ? $options['visible'] : true
        ];
    }
}
