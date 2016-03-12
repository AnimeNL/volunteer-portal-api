<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime;

class EnvironmentTest extends \PHPUnit_Framework_TestCase {
    // Verifies that all configuration files available in this installation can be loaded as valid
    // environments, avoiding silent breakages of less frequently used environments.
    public function testValidConfigurationFiles() {
        $hostnamesTested = 0;

        foreach (new \DirectoryIterator(Environment::CONFIGURATION_DIRECTORY) as $iter) {
            if (!$iter->isFile() || $iter->getExtension() != 'json')
                continue;

            $filename = $iter->getFilename();
            $hostname = substr($filename, 0, -5);

            $environment = Environment::createForHostname($hostname);
            $this->assertTrue($environment->isValid());

            // The values of the getters do not matter, but PHP's type hinting will ensure that a
            // TypeError exception will be thrown when the configuration contains an invalid value.
            $environment->getName();
            $environment->getHostname();
            
            $hostnamesTested++;
        }

        $this->assertGreaterThan(0, $hostnamesTested);
    }

    // Verifies that loading an environment for a non-existing hostname works as expected.
    public function testInvalidConfigurationFiles() {
        $invalidHostEnvironment = Environment::createForHostname('@#$!@#`12oneone');
        $this->assertFalse($invalidHostEnvironment->isValid());

        $unknownHostEnvironment = Environment::createForHostname('unknown.domain.com');
        $this->assertFalse($unknownHostEnvironment->isValid());        
    }

    // Verifies that a given array of settings can be appropriately reflected by the getters made
    // available on the Environment instance.
    public function testSettingReflection() {
        $settings = [
            'name'      => 'Example environment',
            'hostname'  => 'example.com'
        ];

        $environment = Environment::createForTests(true /* valid */, $settings);
        $this->assertTrue($environment->isValid());

        $this->assertEquals($settings['name'], $environment->getName());
        $this->assertEquals($settings['hostname'], $environment->getHostname());
    }

    // Verifies that creating an invalid environment does not rely on any particular properties
    // existing in the passed settings array.
    public function testInvalidEnvironmentCreation() {
        $environment = Environment::createForTests(false /* valid */, []);
        $this->assertFalse($environment->isValid());
    }
}
