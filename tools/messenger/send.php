<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require __DIR__ . '/../../vendor/autoload.php';

$configuration = \Anime\Configuration::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    die('Invalid request method.');

$environment = \Anime\Environment::createForHostname($_SERVER['SERVER_NAME']);
if (!$environment->isValid())
    die('Unrecognized volunteer portal environment.');

if (!array_key_exists('topic', $_POST) || !is_numeric($_POST['topic']))
    die('Topic missing in the POST payload.');

if (!array_key_exists('message', $_POST) || !strlen($_POST['message']))
    die('Message missing in the POST payload.');

$topic = $_POST['topic'];
$message = $_POST['message'];

$volunteers = $environment->loadVolunteers();
if (!($volunteers instanceof \Anime\VolunteerList))
    die('There are no known volunteers.');

$volunteer = $volunteers->findByToken($topic);
if (!($volunteer instanceof \Anime\Volunteer))
    die('Invalid topic.');

$server_key = $configuration->get('firebase/server_key');
$payload = json_encode([
    'to'    => '/topics/' . $topic,
    'data'  => [
        'title'     => 'Message from ' . $environment->getName(),
        'icon'      => '/images/logo-192-2.png',
        'body'      => $message,
        'url'       => 'https://' . $environment->getHostname()
    ]
]);

$result = file_get_contents('https://fcm.googleapis.com/fcm/send', false, stream_context_create([
    'http' => [
        'method'    => 'POST',
        'header'    => [
            'Content-Type: application/json',
            'Authorization: key=' . $server_key
        ],
        'content'   => $payload
    ]
]));

echo $result;
