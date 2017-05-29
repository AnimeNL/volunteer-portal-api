<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/Error.php';

$configuration = \Anime\Configuration::getInstance();

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

// Adds the |$message| to the log file for notification subscriptions. This enables us to keep a
// little bit of insight in what's happening.

function logMessage($message) {
    $line = date('[Y-m-d H:i:s] ') . $_SERVER['REMOTE_ADDR'] . ' ' . trim($message) . PHP_EOL;
    file_put_contents(__DIR__ . '/notifications.log', $line, FILE_APPEND);
}

// All required information is now known in |$subscription|, |$pushSet| and the
// |$volunteer|. Create a relation between the |$subscription| and the token
// used to identify the |$volunteer| as a Firebase Topic.
//
// https://developers.google.com/instance-id/reference/server#create_a_relation_mapping_for_an_app_instance

$server_key = $configuration->get('firebase/server_key');
$url = 'https://iid.googleapis.com/iid/v1/' . $subscription . '/rel/topics/' . $volunteer->getToken();

logMessage('Associating a new subscription for ' . $volunteer->getName() . '...');

$result = file_get_contents($url, false, stream_context_create([
    'http' => [
        'method'    => 'POST',
        'header'    => [
            'Content-Type: application/json',
            'Content-Length: 0',
            'Authorization: key=' . $server_key
        ]
    ]
]));

$result = trim($result);
$success = $result === '{}';  // this is not fragile at all

if ($success)
    logMessage('The subscription was associated with their topic.');
else
    logMessage('The subscription could not be associated with their topic: ' . $result);

echo json_encode([ 'success' => $success ]);

// TODO(peter): Send a welcome notification to the |$subscription|.
