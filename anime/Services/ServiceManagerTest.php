<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

class ServiceManagerTest extends \PHPUnit_Framework_TestCase {
    // Verifies that the service manager's state file exists and is writable by the user that's
    // executing the tests. Without these properties, the service manager cannot function.
    public function testStateFileShouldBeWritable() {
        $this->assertTrue(file_exists(ServiceManager::STATE_FILE));
        $this->assertTrue(is_writable(ServiceManager::STATE_FILE));
    }

    // Verifies that the state file can be loaded and saved in-place. This function will not
    // modify the state in order for it to be safe to re-run tests on installations.
    public function testStateFileLoadAndSave() {
        $serviceManager = new ServiceManager();

        $this->assertTrue($serviceManager->loadState());
        $this->assertTrue($serviceManager->saveState());
    }

    // Verifies that services will be executed in accordance with their frequences. Three services
    // will be faked, each with a different frequency, after which the service manager will be ran
    // over the course of a fake three hours.
    public function testServiceFrequencies() {
        $serviceFactory = function ($frequencyMinutes) {
            // @codingStandardsIgnoreStart
            // CodeSniffer does not yet understand formatting of anonymous classes.
            return new class($frequencyMinutes) implements Service {
                public $counter = 0;
                public $frequencyMinutes;

                public function __construct($frequencyMinutes) {
                    $this->frequencyMinutes = $frequencyMinutes;
                }

                public function getIdentifier() : string {
                    return 'timed-service-' . $this->frequencyMinutes;
                }

                public function getFrequencyMinutes() : int {
                    return $this->frequencyMinutes;
                }

                public function execute() : bool {
                    $this->counter++;
                    return true;
                }
            };
            // @codingStandardsIgnoreEnd
        };

        $serviceManager = new ServiceManager();

        // Create three services, respectively running every 1, 15 and 60 minutes.
        $minuteService = $serviceFactory(1);
        $quarterlyService = $serviceFactory(15);
        $hourlyService = $serviceFactory(60);

        // Register the services with the Service Manager.
        $serviceManager->registerService($minuteService);
        $serviceManager->registerService($quarterlyService);
        $serviceManager->registerService($hourlyService);

        $currentTimestamp = time();

        // Immitate the time passing for three hours by adding to |$currentTimestamp|.
        for ($minute = 0; $minute < 3 * 60; ++$minute)
            $serviceManager->execute($currentTimestamp + $minute * 60);

        // Verify that the services' respective counters are set to the expected values.
        $this->assertEquals(180, $minuteService->counter);
        $this->assertEquals(12, $quarterlyService->counter);
        $this->assertEquals(3, $hourlyService->counter);
    }
}
