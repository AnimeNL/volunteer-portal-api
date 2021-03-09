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

            // Verify that content can be loaded without exceptions for this environment. This may
            // cause quite a bit of file I/O, by loading all content in memory.
            $environment->getContent();

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
        $configuration = Configuration::createForTests([
            'events' => [
                '2021-event'    => [
                    'name'                  => 'PortalCon 2020',
                    'enableContent'         => true,
                    'enableRegistration'    => true,
                    'enableSchedule'        => false,
                    'timezone'              => 'Europe/London',
                ],
            ],
        ]);

        $settings = [
            'contactName'   => 'Name',
            'contactTarget' => 'Target',
            'events'        => [
                '2021-event'    => [
                    // Overrides the registration availability of the global event.
                    'enableRegistration'    => false,
                ],
            ],
            'title'         => 'Title',
        ];

        $environment = Environment::createForTests(
            true /* valid */, $configuration, 'example.com', $settings);

        $this->assertTrue($environment->isValid());
        $this->assertEquals('example.com', $environment->getHostname());

        $this->assertEquals($settings['contactName'], $environment->getContactName());
        $this->assertEquals($settings['contactTarget'], $environment->getContactTarget());
        $this->assertEquals($settings['title'], $environment->getTitle());

        $this->assertEquals(1, count($environment->getEvents()));
        {
            $events = $environment->getEvents();
            $event = $events[0];

            $this->assertTrue($event->isValid());

            $this->assertEquals('PortalCon 2020', $event->getName());
            $this->assertTrue($event->enableContent());
            $this->assertFalse($event->enableRegistration());  // overridden
            $this->assertFalse($event->enableSchedule());
            $this->assertEquals('Europe/London', $event->getTimezone());
            $this->assertNull($event->getWebsite());
        }
    }
}
