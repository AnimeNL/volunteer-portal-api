<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require __DIR__ . '/../vendor/autoload.php';

function dieWithError($error) {
    die(json_encode([ 'error' => $error ]));
}

$environment = \Anime\Environment::createForHostname($_SERVER['HTTP_HOST']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

if (!array_key_exists('token', $_GET) || !is_numeric($_GET['token']))
    dieWithError('Invalid token.');

$volunteers = $environment->loadTeam();
if (!is_array($volunteers))
    dieWithError('There are no known volunteers.');

$token = intval($_GET['token']);

$level = null;
foreach ($volunteers as $volunteer) {
    if (crc32($volunteer['name']) !== $token)
        continue;

    $level = $volunteer['type'];
    break;
}

if ($token === null)
    dieWithError('Invalid token.');

echo '{ "level": "' . $level . '" }';
