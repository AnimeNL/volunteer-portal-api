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

if ($_SERVER['REQUEST_METHOD'] != 'POST')
    dieWithError('This request method is not valid for this API.');

if (!array_key_exists('email', $_POST) || !array_key_exists('accessCode', $_POST))
    dieWithError('Invalid input data given to this API.');

$environment = \Anime\Environment::createForHostname($_SERVER['SERVER_NAME']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

$volunteers = $environment->loadVolunteers();
$volunteer = $volunteers->findByEmail($_POST['email']);

if ($volunteer === null || $volunteer->getAccessCode() != $_POST['accessCode'])
    dieWithError('Unrecognized volunteer login information.');

$configuration = \Anime\Configuration::getInstance();
$sessionTimeoutMinutes = $configuration->get('sessionTimeoutMinutes');

echo json_encode([
    'success'        => true,
    'userToken'      => $volunteer->getUserToken(),
    'authToken'      => $volunteer->getAuthToken(),
    'expirationTime' => (time() + ($sessionTimeoutMinutes * 60)) * 1000,
    'enableDebug'    => $volunteer->isDebug()
]);
