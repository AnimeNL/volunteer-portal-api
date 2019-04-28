<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// Object representing a list of volunteers for a given environment. The list is iterable, countable
// and searchable, and contains convenience methods that may be useful. It will be immutable after
// construction, and attempts to modify it will result in an exception.
class VolunteerList implements \ArrayAccess, \Countable, \IteratorAggregate {
    // Creates a new instance of the VolunteerList based on |$volunteerData|, each of which will
    // be loaded in to a new instance of the Volunteer class.
    public static function create(array $volunteerData) : VolunteerList {
        $volunteers = [];

        // Volunteer::__construct is expected to throw for invalid data.
        foreach ($volunteerData as $data)
            $volunteers[] = new Volunteer($data);

        return new VolunteerList($volunteers);
    }

    private $volunteers;

    private function __construct(array $volunteers) {
        $this->volunteers = $volunteers;
    }

    // Finds the volunteer named |$name|. If |$fuzzy| is set to true, differences in spacing and
    // non-alphabetic characters will be ignored. Returns the Volunteer object when found, or NULL
    // in case no suitable volunteer could be found. This method has O(n) complexity.
    public function findByName(string $name, $fuzzy = false) : ?Volunteer {
        if ($fuzzy)
            $name = $this->normalizeNameForFuzzyMatching($name);

        foreach ($this->volunteers as $volunteer) {
            $volunteerName = $volunteer->getName();

            if ($fuzzy)
                $volunteerName = $this->normalizeNameForFuzzyMatching($volunteerName);

            if ($volunteerName === $name)
                return $volunteer;
        }

        return null;
    }

    // Finds the volunteer associated with |$token|, or NULL when they cannot be found. This method
    // has O(n) complexity because it has to iterate over all volunteers.
    public function findByToken(string $token) : ?Volunteer {
        foreach ($this->volunteers as $volunteer) {
            if ($volunteer->getToken() == $token)
                return $volunteer;
        }

        return null;
    }

    // Normalizes |$name| by lower casing it and removing all non-alphabetic characters, including
    // numbers and spaces. Only to be used for fuzzy matching in the find() method.
    private function normalizeNameForFuzzyMatching(string $name) : string {
        $expression = '/&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);/i';

        $name = preg_replace($expression, '$1', htmlentities($name, ENT_QUOTES, 'UTF-8'));
        $name = strtolower($name);

        return preg_replace('/[^a-z]/', '', $name);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////
    // ArrayAccess interface

    public function offsetExists($offset) {
        return is_integer($offset) && $offset >= 0 && $offset < count($this->volunteers);
    }

    public function offsetGet($offset) {
        if ($this->offsetExists($offset))
            return $this->volunteers[$offset];

        return null;
    }

    public function offsetSet($offset, $value) {
        throw new \Exception('The VolunteerList instance is immutable.');
    }

    public function offsetUnset($offset) {
        throw new \Exception('The VolunteerList instance is immutable.');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////
    // Countable interface

    public function count() {
        return count($this->volunteers);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////
    // IteratorAggregate interface

    public function getIterator() {
        return new \ArrayIterator($this->volunteers);
    }
}
