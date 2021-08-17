<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting((E_ALL | E_STRICT) & ~E_WARNING);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';

Header('Access-Control-Allow-Origin: *');
Header('Content-Type: application/json');

$endpoint = $_SERVER['REQUEST_URI'];
$parameters = [];

if (str_contains($endpoint, '?')) {
    [ $endpoint, $parameterString ] = explode('?', $endpoint, 2);

    // Parse the |$parameterString| into |$parameters| as an associative array.
    parse_str($parameterString, $parameters);
}

$api = new \Anime\Api($_SERVER['HTTP_HOST']);
switch ($endpoint) {
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
    case '/api/content':
        echo json_encode($api->content());
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
        echo json_encode([ 'error' => 'Unknown API endpoint: ' . $endpoint ]);
        break;
}
