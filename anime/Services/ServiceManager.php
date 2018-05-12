<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// The Service Manager is responsible for tracking and executing services when their execution is
// due. It will also keep state of failures, and inform the administrators of them.
class ServiceManager {
    // File in which the Service Manager will write the current state.
    // Marked as public for testing purposes only.
    public const STATE_FILE = __DIR__ . '/state.json';

    private $serviceLog;
    private $services;
    private $state;

    // Initializes a new service manager using |$serviceLog| for sharing success status.
    public function __construct(ServiceLog $serviceLog) {
        $this->serviceLog = $serviceLog;
        $this->services = [];
        $this->state = [];
    }

    // Loads the current service manager state from |STATE_FILE| and returns whether the state could
    // be loaded successfully. It will be stored in the |$state| member of the instance.
    public function loadState() : bool {
        if (!file_exists(ServiceManager::STATE_FILE) || !is_readable(ServiceManager::STATE_FILE))
            return false;  // unable to open the file for reading

        $stateData = file_get_contents(ServiceManager::STATE_FILE);
        $state = json_decode($stateData, true /* associative */);

        if ($state === null)
            return false;  // invalid json data within the file

        $this->state = $state;
        return true;
    }

    // Saves the current service manager state to |STATE_FILE| and returns whether it could be
    // stored successfully. State will not persist when this fails.
    public function saveState() : bool {
        $directoryName = dirname(ServiceManager::STATE_FILE);
        if (!is_writeable(ServiceManager::STATE_FILE) && !is_writable($directoryName))
            return false;  // unable to open the file for reading

        $stateData = json_encode($this->state);
        if (file_put_contents(self::STATE_FILE, $stateData) !== strlen($stateData))
            return false;  // not all data could be written to the file.

        return true;
    }

    // Registers |$service| as a service that may have to be executed by this manager. Applicability
    // will be determined elsewhere, this method merely registers the |$service|.
    public function registerService(Service $service) : void {
        $this->services[] = $service;
    }

    // Executes the services that are up for execution according to their stored state and indicated
    // frequency. Services with no known state will be executed regardless. The |$timeForTesting|
    // parameter may be set to a Unix timestamp only for the purposes of running unit tests, which
    // can be ignored entirely by setting |$force| to TRUE.
    public function execute($force, $timeForTesting = 0) : void {
        $time = $timeForTesting ?: time();

        $executionQueue = [];
        foreach ($this->services as $service) {
            $identifier = $service->getIdentifier();
            if (array_key_exists($identifier, $this->state)) {
                $frequencyMinutes = $service->getFrequencyMinutes();
                $stalenessTime = $time - $frequencyMinutes * 60;

                if (!$force && $this->state[$identifier] > $stalenessTime)
                    continue;
            }

            $executionQueue[] = $service;
        }

        foreach ($executionQueue as $service) {
            $identifier = $service->getIdentifier();

            $startTime = microtime(true);
            try {
                $success = $service->execute();
                $runtime = microtime(true) - $startTime;

                $this->serviceLog->onServiceExecuted($identifier, $runtime);

            } catch (\Throwable $throwable) {
                if (array_key_exists('TERM', $_SERVER))
                    echo $throwable;

                $runtime = microtime(true) - $startTime;
                $this->serviceLog->onServiceException($identifier, $runtime, $throwable);
            }

            $this->state[$identifier] = $time;
        }

        $this->serviceLog->onFinish();
    }
}
