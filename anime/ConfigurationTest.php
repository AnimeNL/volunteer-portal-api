<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime;

class ConfigurationTest extends \PHPUnit\Framework\TestCase {
    // Verifies that the default configuration can be loaded successfully.
    public function testDefaultConfiguration() {
        if (getenv('TRAVIS_CI') !== false)
            return;  // this test doesn't make sense when ran through Travis

        $this->assertTrue(Configuration::getInstance() instanceof Configuration);
        $this->assertSame(Configuration::getInstance(), Configuration::getInstance());
    }

    // Verifies that getting data from the Configuration class works as expected.
    public function testReadConfiguration() {
        $configuration = Configuration::createForTests([
            'hello'     => 'world',
            'nested'    => [
                'value'     => 'foobar',
                'number'    => 42,
                'array'     => [10, 20, 30],
                'nested'    => [
                    'value'     => 'bazboq'
                ]
            ]
        ]);

        $this->assertEquals('world', $configuration->get('hello'));
        $this->assertEquals('foobar', $configuration->get('nested/value'));
        $this->assertEquals(42, $configuration->get('nested/number'));
        $this->assertEquals([10, 20, 30], $configuration->get('nested/array'));
        $this->assertEquals('bazboq', $configuration->get('nested/nested/value'));
    }
}
