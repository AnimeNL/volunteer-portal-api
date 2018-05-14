<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/Error.php';

if (!array_key_exists('token', $_GET) || !is_numeric($_GET['token']))
    dieWithError('Invalid token.');

$token = intval($_GET['token']);

$environment = \Anime\Environment::createForHostname($_SERVER['SERVER_NAME']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

$volunteers = $environment->loadVolunteers();
if (!($volunteers instanceof \Anime\VolunteerList))
    dieWithError('There are no known volunteers.');

$volunteer = $volunteers->findByToken($token);
if (!($volunteer instanceof \Anime\Volunteer))
    dieWithError('Invalid token.');

// By default volunteers only have access to their own environment. Senior and Staff members get
// access to all volunteering environments.
$environments = $volunteer->isSeniorVolunteer() ? \Anime\Environment::getAll()
                                                : [];

// Make sure that |$environment| is always used for the current context, since its list of
// volunteers  has already been initialized.
$environments[$_SERVER['SERVER_NAME']] = $environment;

// The ConventionData class is in charge of making the actual data selections.
die(json_encode(\Anime\ConventionData::compileForVolunteer($environments, $environment,
                                                           $volunteer)));
