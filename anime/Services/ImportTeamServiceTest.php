<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

class ImportTeamServiceTest extends \PHPUnit_Framework_TestCase {
    // Verifies that the given configuration options will be reflected in the getters.
    public function testOptionGetters() {
        $service = new ImportTeamService([
            'frequency'     => 42,
            'identifier'    => 'import-team-service',
            'source'        => 'https://data/source.csv'
        ]);

        $this->assertEquals(42, $service->getFrequencyMinutes());
        $this->assertEquals('import-team-service', $service->getIdentifier());
    }
}
