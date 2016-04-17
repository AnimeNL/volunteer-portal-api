<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require __DIR__ . '/../vendor/autoload.php';

function dieWithError($error) {
    die(json_encode([ 'error' => $error ]));
}

if (!array_key_exists('token', $_GET) || !is_numeric($_GET['token']))
    dieWithError('Invalid token.');

$token = intval($_GET['token']);

$environment = \Anime\Environment::createForHostname($_SERVER['HTTP_HOST']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

$volunteers = $environment->loadVolunteers();
if (!($volunteers instanceof \Anime\VolunteerList))
    dieWithError('There are no known volunteers.');

$volunteer = $volunteers->findByToken($token);
if (!($volunteer instanceof \Anime\Volunteer))
    dieWithError('Invalid token.');

// The ConventionData class is in charge of making the actual data selections.
die(json_encode(\Anime\ConventionData::CompileForVolunteer($environment, $volunteer)));
