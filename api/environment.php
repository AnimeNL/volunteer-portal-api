<?php
// Copyright 2019 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/error.php';

Header('Access-Control-Allow-Origin: *');
Header('Content-Type: application/json');

$environment = \Anime\Environment::createForHostname($_SERVER['HTTP_HOST']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

echo json_encode([
    'eventName'   => 'AnimeCon 2019',
    'portalTitle' => $environment->getName(),
    'seniorTitle' => $environment->typeToTitle(\Anime\Volunteer::TYPE_SENIOR),
    'timezone'    => 'Europe/Amsterdam',
    'year'        => $environment->getYear()
]);
