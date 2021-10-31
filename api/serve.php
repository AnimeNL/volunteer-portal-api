<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require __DIR__ . '/../vendor/autoload.php';

Header('Access-Control-Allow-Origin: *');
Header('Content-Type: application/json');

\Anime\ErrorHandler::Install();

$requestUri = $_SERVER['REQUEST_URI'];
$parameters = [];

if (str_contains($requestUri, '?')) {
    [ $requestUri, $parameterString ] = explode('?', $requestUri, 2);

    // Parse the |$parameterString| into |$parameters| as an associative array.
    parse_str($parameterString, $parameters);
}

$api = new \Anime\Api($_SERVER['HTTP_HOST']);
$endpoint = null;

switch ($requestUri) {
    case '/api/application':
        $endpoint = new \Anime\Endpoints\ApplicationEndpoint;
        break;
    case '/api/auth':
        $endpoint = new \Anime\Endpoints\AuthEndpoint;
        break;
    case '/api/content':
        $endpoint = new \Anime\Endpoints\ContentEndpoint;
        break;
    case '/api/environment':
        $endpoint = new \Anime\Endpoints\EnvironmentEndpoint;
        break;
    case '/api/event':
        $endpoint = new \Anime\Endpoints\EventEndpoint;
        break;
    case '/api/user':
        $endpoint = new \Anime\Endpoints\UserEndpoint;
        break;
}

if (!$endpoint) {
    echo json_encode([ 'error' => 'Unknown API endpoint: ' . $requestUri ]);
    exit;
}

$validationResult = $endpoint->validateInput($parameters, $_POST);
if ($validationResult !== true) {
    $message = 'A validation error occurred while calling this API endpoint.';
    if (is_string($validationResult))
        $message = $validationResult;

    echo json_encode([ 'error' => $message ]);
    exit;
}

echo json_encode($endpoint->execute($api, $parameters, $_POST));
