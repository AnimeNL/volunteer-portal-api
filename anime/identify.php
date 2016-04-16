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

$volunteers = $environment->loadTeam();
if (!is_array($volunteers))
    dieWithError('There are no known volunteers.');

// For the purposes of identification, the |$name| will be lowercased and any non-alphabetic
// characters will be removed, which includes spaces.
function normalizeNameForIdentification($name) {
    return preg_replace('/[^a-z]/i', '', strtolower($name));
}

$requestName = normalizeNameForIdentification(file_get_contents('php://input'));
if (strlen($requestName) < 5)
    dieWithError('Your name must have more than five characters.');

foreach ($volunteers as $volunteer) {
    if (normalizeNameForIdentification($volunteer['name']) !== $requestName)
        continue;

    $userInfo = [
        'name'  => $volunteer['name'],
        'token' => strval(crc32($volunteer['name']))
    ];

    die(json_encode($userInfo));
}

dieWithError('Your name has not been recognized.');
