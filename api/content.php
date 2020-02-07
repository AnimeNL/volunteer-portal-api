<?php
// Copyright 2019 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting((E_ALL | E_STRICT) & ~E_WARNING);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/error.php';

Header('Access-Control-Allow-Origin: *');
Header('Content-Type: application/json');

$environment = \Anime\Environment::createForHostname($_SERVER['HTTP_HOST']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

$content = null;

switch ($environment->getHostname()) {
    case 'stewards.team':
        $content = [
            '/'                                 => 'index.md',
            '/404'                              => 'not-found.md',

            // Public pages part of the Registration application.
            '/registration/'                    => 'registration-index.md',
            '/registration/dataverwerking.html' => 'registration-dataverwerking.md',
            '/registration/faq.html'            => 'registration-faq.md',
            '/registration/hotel.html'          => 'registration-hotel.md',
            '/registration/rooster.html'        => 'registration-rooster.md',
            '/registration/training.html'       => 'registration-training.md',

            // Internal pages part of the Registration application.
            '/registration/internal/confirm'    => 'registration-form-confirm.md',
            '/registration/internal/intro'      => 'registration-form-intro.md',
        ];
        break;

    default:
        dieWithError('This method is not available for this team yet.');
}

$lastUpdated = time();
$pages = [];

foreach ($content as $url => $contentFilename) {
    $contentPath = __DIR__ . '/content/' . $environment->getHostname() . '/' . $contentFilename;

    $lastUpdated = max($lastUpdated, filemtime($contentPath));
    $pages[] = [
        'url'       => $url,
        'content'   => file_get_contents($contentPath)
    ];
}

echo json_encode([
    'lastUpdate'    => $lastUpdated,
    'pages'         => $pages
]);
