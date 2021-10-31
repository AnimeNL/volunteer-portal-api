<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting((E_ALL | E_STRICT) & ~E_WARNING);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';

Header('Access-Control-Allow-Origin: *');
Header('Content-Type: application/json');

function dispatchErrorMessage($errorMessage) {
    $message = new \Nette\Mail\Message;
    $message->setFrom('anime@' . $_SERVER['HTTP_HOST']);
    $message->addTo('peter@animecon.nl');

    ob_start();
    var_dump($_POST);
    $postContents = ob_get_clean();

    $message->setSubject('Volunteer Portal Exception');
    $message->setHtmlBody($errorMessage . '<br /><br /><pre>' . $postContents);

    $mailer = new \Nette\Mail\SendmailMailer;
    $mailer->send($message);
}

set_exception_handler(function ($exception) {
    $message  = $exception->getMessage() . ' (' . $exception->getCode() . ') in ';
    $message .= $exception->getFile() . ':' . $exception->getLine() . '<br /><br />';
    $message .= $exception->getTraceAsString();

    dispatchErrorMessage($message);
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    dispatchErrorMessage($errstr . ' (' . $errno . ') in ' . $errfile . ':' . $errline);
}, E_ALL ^ E_NOTICE);

$requestUri = $_SERVER['REQUEST_URI'];
$parameters = [];

if (str_contains($requestUri, '?')) {
    [ $requestUri, $parameterString ] = explode('?', $requestUri, 2);

    // Parse the |$parameterString| into |$parameters| as an associative array.
    parse_str($parameterString, $parameters);
}

// Helper function to validate whether a POST value is a stringified boolean.
function isBool(string $value): bool {
    return $value === 'true' || $value === 'false';
}

// Helper function to translate a stringified boolean to an actual boolean.
function toBool(string $value): bool {
    if (!isBool($value))
        throw new Error('Invalid boolean values may not be converted: ' . $value);

    return $value === 'true';
}

$api = new \Anime\Api($_SERVER['HTTP_HOST']);
$endpoint = null;

switch ($requestUri) {
    case '/api/content':
        $endpoint = new \Anime\Endpoints\ContentEndpoint;
        break;
}

if ($endpoint) {
    if (!$endpoint->validateInput($parameters, $_POST))
        die();

    echo json_encode($endpoint->execute($api, $parameters, $_POST));
    die();
}

switch ($requestUri) {
    // https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apiapplication
    case '/api/application':
        if (!array_key_exists('event', $_POST) || !strlen($_POST['event'])) {
            echo json_encode([ 'error' => 'No event to register for has been supplied.' ]);
        } else if (!array_key_exists('firstName', $_POST) || !strlen($_POST['firstName'])) {
            echo json_encode([ 'error' => 'Please enter your first name.' ]);
        } else if (!array_key_exists('lastName', $_POST) || !strlen($_POST['lastName'])) {
            echo json_encode([ 'error' => 'Please enter your last name.' ]);
        } else if (!array_key_exists('dateOfBirth', $_POST) ||
                       !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $_POST['dateOfBirth'])) {
            echo json_encode([ 'error' => 'Please enter a valid date of birth (YYYY-MM-DD).' ]);
        } else if (!array_key_exists('emailAddress', $_POST) ||
                       !filter_var($_POST['emailAddress'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode([ 'error' => 'Please enter a valid e-mail address.' ]);
        } else if (!array_key_exists('phoneNumber', $_POST) || !strlen($_POST['phoneNumber'])) {
            echo json_encode([ 'error' => 'Please enter your phone number.' ]);
        } else if (!array_key_exists('gender', $_POST) || !strlen($_POST['gender'])) {
            echo json_encode([ 'error' => 'Please enter your preferred gender.' ]);
        } else if (!array_key_exists('shirtSize', $_POST) || !strlen($_POST['shirtSize'])) {
            echo json_encode([ 'error' => 'Please enter your preferred t-shirt size.' ]);
        } else if (!array_key_exists('preferences', $_POST)) {
            echo json_encode([ 'error' => 'Please enter your volunteering preferences.' ]);
        } else if (!array_key_exists('available', $_POST) || !isBool($_POST['available'])) {
            echo json_encode([ 'error' => 'Please enter whether you are available.' ]);
        } else if (!array_key_exists('hotel', $_POST) || !isBool($_POST['hotel'])) {
            echo json_encode([ 'error' => 'Please enter whether you would like a hotel.' ]);
        } else if (!array_key_exists('whatsApp', $_POST) || !isBool($_POST['whatsApp'])) {
            echo json_encode([ 'error' => 'Please indicate whether to join the WhatsApp group.' ]);
        } else if (!array_key_exists('covidRequirements', $_POST) || !$_POST['covidRequirements']) {
            echo json_encode([ 'error' => 'You must agree with the COVID-19 requirements.' ]);
        } else if (!array_key_exists('gdprRequirements', $_POST) || !$_POST['gdprRequirements']) {
            echo json_encode([ 'error' => 'You must agree with the GDPR requirements.' ]);
        } else {
            echo json_encode($api->application(
                $_POST['event'], $_POST['firstName'], $_POST['lastName'], $_POST['dateOfBirth'],
                $_POST['emailAddress'], $_POST['phoneNumber'], $_POST['gender'],
                $_POST['shirtSize'], $_POST['preferences'], toBool($_POST['available']),
                toBool($_POST['hotel']), toBool($_POST['whatsApp'])));
        }
        break;

    // https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apiauth
    case '/api/auth':
        if (!array_key_exists('emailAddress', $_POST))
            echo json_encode([ 'error' => 'Missing parameter: emailAddress' ]);
        else if (!array_key_exists('accessCode', $_POST))
            echo json_encode([ 'error' => 'Missing parameter: accessCode' ]);
        else
            echo json_encode($api->auth($_POST['emailAddress'], $_POST['accessCode']));

        break;

    // https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apienvironment
    case '/api/environment':
        echo json_encode($api->environment());
        break;

    case '/api/event':
        if (!array_key_exists('authToken', $parameters))
            echo json_encode([ 'error' => 'Missing parameter: authToken' ]);
        else if (!array_key_exists('event', $parameters))
            echo json_encode([ 'error' => 'Missing parameter: event' ]);
        else
            echo json_encode($api->event($parameters['authToken'], $parameters['event']));

        break;

    // https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apienvironment
    case '/api/user':
        if (!array_key_exists('authToken', $parameters))
            echo json_encode([ 'error' => 'Missing parameter: authToken' ]);
        else
            echo json_encode($api->user($parameters['authToken']));

        break;

    default:
        echo json_encode([ 'error' => 'Unknown API endpoint: ' . $requestUri ]);
        break;
}
