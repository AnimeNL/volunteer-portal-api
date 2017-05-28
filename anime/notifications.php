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

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    dieWithError('Invalid request method.');

if (!array_key_exists('subscription', $_POST) || !array_key_exists('pushSet', $_POST))
    dieWithError('Required POST field missing.');

$subscription = $_POST['subscription'];
$pushSet = $_POST['pushSet'];

echo json_encode([
    'volunteer' => $volunteer->getName(),
    'subscription' => $subscription,
    'pushSet' => $pushSet
]);

