<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime;

// Provides a series of helper functions to validate untrusted input or configuration.
class Validation {
    // Validates the given |$settings| as configuration for an individual event.
    // https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#response-ienvironmentresponseevent
    public static function validateEvent(array $settings): bool {
        return Validation::validateStructure($settings, 'Event', [
            [
                'property' => 'name',
                'type' => 'string'
            ],
            [
                'property' => 'enableContent',
                'type' => 'bool'
            ],
            [
                'property' => 'enableRegistration',
                'type' => 'bool'
            ],
            [
                'property' => 'enableSchedule',
                'type' => 'bool'
            ],
            [
                'property' => 'dates',
                'type' => 'array[dates]',
            ],
            [
                'property' => 'timezone',
                'type' => 'string'
            ],
            [
                'property' => 'website',
                'type' => 'string',
                'optional' => true
            ],
        ]);
    }

    // ---------------------------------------------------------------------------------------------

    // Validates the given |$input| against the given |$structure|.
    private static function validateStructure(array $input, string $type, array $structure): bool {
        foreach ($structure as $field) {
            $optional = array_key_exists('optional', $field) && !!$field['optional'];
            $prefix = 'Property ' . $type . '[' . $field['property'] . ']';

            if (!array_key_exists($field['property'], $input)) {
                if (!$optional)
                    throw new \Exception($prefix . ' does not exist on the input.');

                continue;
            }

            $value = $input[$field['property']];
            switch ($field['type']) {
                case 'array[dates]':
                    if (!is_array($value))
                        throw new \Exception($prefix . ' was expected to be an array.');

                    if (count($value) !== 2)
                        throw new \Exception($prefix . ' was expected have two entries.');

                    foreach ($value as $date) {
                        if (!preg_match('/^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$/s', $date))
                            throw new \Exception($prefix . ' was expected to contain `YYYY-MM-DD HH:II:ss` values.');
                    }

                    break;

                case 'bool':
                    if (!is_bool($value))
                        throw new \Exception($prefix . ' was expected to be a boolean.');

                    break;

                case 'string':
                    if (!is_string($value))
                        throw new \Exception($prefix . ' was expected to be a string.');

                    break;
            }
        }

        return true;
    }
}
