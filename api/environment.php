<?php
// Copyright 2019 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting((E_ALL | E_STRICT) & ~E_WARNING);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/error.php';

Header('Access-Control-Allow-Origin: *');
Header('Content-Type: application/json');

$environment = \Anime\Environment::createForHostname($_SERVER['HTTP_HOST']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

echo json_encode([
    'contactName'   => 'Peter',
    'contactTarget' => 'mailto:' . $environment->getContact(),
    'title'         => $environment->getName(),

    'events' => [
        [
            'name'                  => 'AnimeCon 2020: Classic',
            'enableContent'         => true,
            'enableRegistration'    => false,
            'enableSchedule'        => false,
            'slug'                  => '2020-classic',
            'timezone'              => 'Europe/Amsterdam',
            'website'               => 'https://www.animecon.nl/',
        ],
        [
            'name'                  => 'AnimeCon 2021',
            'enableContent'         => false,
            'enableRegistration'    => false,
            'enableSchedule'        => false,
            'slug'                  => '2021',
            'timezone'              => 'Europe/Amsterdam',
            'website'               => 'https://www.animecon.nl/',
        ],
    ],
]);
