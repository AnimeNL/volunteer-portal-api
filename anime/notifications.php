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

// All required information is now known in |$subscription|, |$pushSet| and the |$volunteer|. Create
// a relation between the |$subscription| and the token used to identify the |$volunteer| as a
// Firebase Cloud Messaging Topic.
//
// https://developers.google.com/instance-id/reference/server#create_a_relation_mapping_for_an_app_instance

logMessage('Associating a new subscription for ' . $volunteer->getName() . ' (topic: ' . $volunteer->getToken() . ')...');

$success = \Anime\PushUtilities::subscribeToTopic($subscription, $volunteer->getToken());
if ($success) {
    logMessage('The subscription was associated with their topic.');

    // Immediately send a message to the |$subscription| to let them know they are now subscribed to
    // receiving notifications. Yay for fast server-side propagation on their end.

    $notification = new \Anime\PushNotification('Notifications activated! 😍', [
        'body'      => 'You will now receive reminders for your upcoming shifts!',
        'icon'      => '/images/subscribed.png',
        'vibrate'   => [ 500, 110, 500, 110, 450, 110, 200, 110, 170, 40, 450, 110, 200, 110, 170, 40, 500 ]
    ]);

    \Anime\PushUtilities::sendToSubscription($subscription, $notification);

    logMessage('They have received a welcome notification.');
} else {
    logMessage('The subscription could not be associated with their topic: ' . $result);
}

// And report back to the browser that the subscription was successful.

echo json_encode([ 'success' => $success ]);
