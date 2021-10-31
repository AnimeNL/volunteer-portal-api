<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

require __DIR__ . '/../../../vendor/autoload.php';

\Anime\ErrorHandler::Install();

// Pass -f or --force to force immediate execution of all services.
$force = count(getopt('f', [ 'force' ])) > 0;

// This file is the entry-point for executing the periodic services. It is adviced to create a cron
// job that runs every minute (for best frequency accuracy) executing this file.

$serviceLog = new ServiceLogImpl();
$serviceManager = new ServiceManager($serviceLog);
$serviceManager->loadState();

// Register all the services known to the configuration file with the |$serviceManager|.
foreach (\Anime\Configuration::getInstance()->get('services') as $service) {
    if (!array_key_exists('class', $service) || !array_key_exists('frequency', $service)
            || !array_key_exists('identifier', $service)) {
        $serviceLog->onSystemError('Incomplete service configuration found, ignoring.');
        continue;
    }

    $options = [];
    if (array_key_exists('options', $service) && is_array($service['options']))
        $options = $service['options'];

    $class = __NAMESPACE__ . '\\' . $service['class'];
    $serviceManager->registerService(
            new $class($options, $service['frequency'], $service['identifier']));
}

$serviceManager->execute($force);
$serviceManager->saveState();
