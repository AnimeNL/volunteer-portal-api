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

if (!array_key_exists('authToken', $_GET))
    dieWithError('Invalid input data given to this API.');

$environment = \Anime\Environment::createForHostname($_SERVER['SERVER_NAME']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

$volunteers = $environment->loadVolunteers();
$volunteer = $volunteers->findByAuthToken($_GET['authToken']);

if (!$volunteer)
    dieWithError('Unrecognized volunteer login information.');

$eventData = new \Anime\EventData($environment, $volunteer);

echo json_encode([
    'success'           => true,
    'events'            => $eventData->getEvents(),
    'floors'            => $eventData->getFloors(),
    'internalNotes'     => $eventData->getInternalNotes(),
    'locations'         => $eventData->getLocations(),
    'shifts'            => $eventData->getShifts(),
    'volunteerGroups'   => $eventData->getVolunteerGroups(),
    'volunteers'        => $eventData->getVolunteers()
]);
