<?php
// Copyright 2019 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting((E_ALL | E_STRICT) & ~E_WARNING);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/anime/Services/generateAccessCode.php';
require __DIR__ . '/error.php';

Header('Access-Control-Allow-Origin: *');
Header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] != 'POST')
    dieWithError('This request method is not valid for this API.');

if (!array_key_exists('email', $_POST) || !array_key_exists('accessCode', $_POST))
    dieWithError('Invalid input data given to this API.');

$environment = \Anime\Environment::createForHostname($_SERVER['HTTP_HOST']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

$database = $environment->createDatabase(/* readOnly= */ true);

$registration = $database->findRegistrationByEmailAddress($_POST['email']);
if (is_null($registration))
    dieWithError('Unknown volunteer login information.');

$configuration = \Anime\Configuration::getInstance();
$sessionTimeoutMinutes = $configuration->get('sessionTimeoutMinutes');

echo json_encode([
    'success'        => true,
    'userName'       => $registration->getDisplayName(),
    'userToken'      => $registration->getUserToken(),
    'authToken'      => $registration->getAuthToken(),
    'expirationTime' => (time() + ($sessionTimeoutMinutes * 60)) * 1000,
    'abilities'      => [],  // TODO
]);
