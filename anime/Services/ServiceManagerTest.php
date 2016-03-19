<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

class ServiceManagerTest extends \PHPUnit_Framework_TestCase {
    // Creates a new ServiceLog that can be used for the purposes of testing. The implementation
    // writes all received callbacks, in order, to the |$log| member part of the instance. The
    // |$runtime| of the callbacks will be ignored as it would make the tests non-deterministic.
    private function createServiceLog() : ServiceLog {
        // @codingStandardsIgnoreStart
        // CodeSniffer does not yet understand formatting of anonymous classes.
        return new class implements ServiceLog {
            public $finished = 0;
            public $log = [];

            public function onFinish() {
                $this->finished++;
            }

            public function onServiceExecuted(string $identifier, float $runtime) {
                $this->log[] = ['executed', $identifier];
            }

            public function onServiceException(string $identifier, float $runtime, $exception) {
                $this->log[] = ['exception', $identifier, $exception->getMessage()];
            }

        };
        // @codingStandardsIgnoreEnd
    }

    // Verifies that the service manager's state file exists and is writable by the user that's
    // executing the tests. Without these properties, the service manager cannot function.
    public function testStateFileShouldBeWritable() {
        $this->assertTrue(file_exists(ServiceManager::STATE_FILE));
        $this->assertTrue(is_writable(ServiceManager::STATE_FILE));
    }

    // Verifies that the state file can be loaded and saved in-place. This function will not
    // modify the state in order for it to be safe to re-run tests on installations.
    public function testStateFileLoadAndSave() {
        $serviceManager = new ServiceManager($this->createServiceLog());

        $this->assertTrue($serviceManager->loadState());
        $this->assertTrue($serviceManager->saveState());
    }

    // Verifies that services will be executed in accordance with their frequences. Three services
    // will be faked, each with a different frequency, after which the service manager will be ran
    // over the course of a fake three hours.
    public function testServiceFrequencies() {
        $serviceFactory = function (int $frequencyMinutes) {
            // @codingStandardsIgnoreStart
            // CodeSniffer does not yet understand formatting of anonymous classes.
            return new class($frequencyMinutes) implements Service {
                public $counter = 0;
                public $frequencyMinutes;

                public function __construct(int $frequencyMinutes) {
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

        $serviceLog = $this->createServiceLog();
        $serviceManager = new ServiceManager($serviceLog);

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

        // Verify that an equal number of log entries have been written to the log.
        $this->assertEquals(195, count($serviceLog->log));
    }

    // Verifies that entries will be written to the service log as expected, and generate either
    // `executed` or `exception` messages depending on a service's result.
    public function testServiceLog() {
        $serviceFactory = function (string $identifier, callable $callback) {
            // @codingStandardsIgnoreStart
            // CodeSniffer does not yet understand formatting of anonymous classes.
            return new class($identifier, $callback) implements Service {
                public $callback;
                public $identifier;

                public function __construct(string $identifier, callable $callback) {
                    $this->identifier = $identifier;
                    $this->callback = $callback;
                }

                public function getIdentifier() : string {
                    return $this->identifier;
                }

                public function getFrequencyMinutes() : int {
                    return 1;
                }

                public function execute() {
                    return ($this->callback)();
                }
            };
            // @codingStandardsIgnoreEnd
        };

        $serviceLog = $this->createServiceLog();
        $serviceManager = new ServiceManager($serviceLog);

        // Register three services in order: one that succeeds and one that throws.
        $serviceManager->registerService($serviceFactory('id-succeeds', function () {
            return true;
        }));

        $serviceManager->registerService($serviceFactory('id-throws', function () {
            $result = 42 / 0;
            return true;
        }));

        // Execute the service manager. All services will be executed immediately.
        $serviceManager->execute();

        // Verify that the data in the service log is what we expect it to be.
        $this->assertEquals(2, count($serviceLog->log));
        $this->assertEquals([
            ['executed', 'id-succeeds'],
            ['exception', 'id-throws', 'Division by zero']
        ], $serviceLog->log);

        $this->assertEquals(1, $serviceLog->finished);
    }
}
