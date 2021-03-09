<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime;

class EnvironmentTest extends \PHPUnit\Framework\TestCase {
    // Verifies that all environment hostnames available in the configuration be loaded as valid
    // environments, avoiding silent breakages of less frequently used environments.
    public function testConfigurationValidity() {
        $hostnamesTested = 0;

        $configuration = Configuration::getInstance();
        foreach (Environment::all($configuration) as $environment) {
            $this->assertTrue($environment->isValid());

            // The values of the getters do not matter, but PHP's type hinting will ensure that a
            // TypeError exception will be thrown when the configuration contains an invalid value.
            $environment->getContactName();
            $environment->getContactTarget();
            $environment->getTitle();

            $hostnamesTested++;
        }

        $this->assertGreaterThan(0, $hostnamesTested);
    }

    // Verifies that loading an environment for a non-existing hostname works as expected.
    public function testInvalidHostnames() {
        $configuration = Configuration::getInstance();

        $invalidHostEnvironment =
            Environment::createForHostname($configuration, '@#$!@#`12oneone');
        $this->assertFalse($invalidHostEnvironment->isValid());

        $unknownHostEnvironment =
            Environment::createForHostname($configuration, 'unknown.domain.com');
        $this->assertFalse($unknownHostEnvironment->isValid());
    }

    // Verifies that a given array of settings can be appropriately reflected by the getters made
    // available on the Environment instance.
    public function testSettingReflection() {
        $settings = [
            'contactName'   => 'Name',
            'contactTarget' => 'Target',
            'title'         => 'Title',
        ];

        $environment = Environment::createForTests(true /* valid */, $settings);
        $this->assertTrue($environment->isValid());

        $this->assertEquals($settings['contactName'], $environment->getContactName());
        $this->assertEquals($settings['contactTarget'], $environment->getContactTarget());
        $this->assertEquals($settings['title'], $environment->getTitle());
    }
}
