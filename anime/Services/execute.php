<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

use Anime\Configuration;

require __DIR__ . '/../../vendor/autoload.php';

// Pass -f or --force to force immediate execution of all services.
$force = count(getopt('f', [ 'force' ])) > 0;

// This file is the entry-point for executing the periodic services. It is adviced to create a cron
// job that runs every minute (for best frequency accuracy) executing this file.

$serviceLog = new ServiceLogImpl();
$serviceManager = new ServiceManager($serviceLog);
$serviceManager->loadState();

// Register all the services known to the configuration file with the |$serviceManager|.
foreach (Configuration::getInstance()->get('services') as $service)
    $serviceManager->registerService(new $service['class']($service['options']));

$serviceManager->execute($force);
$serviceManager->saveState();
