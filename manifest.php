<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require_once __DIR__ . '/anime/Environment.php';

$environment = Anime\Environment::createForHostname($_SERVER['SERVER_NAME']);
if (!$environment->isValid()) {
    Header('HTTP/1.0 404 Not Found');
    exit;
}

// Send the appopriate Content-Type value for manifest files.
Header('Content-Type: application/manifest+json');
?>
{
    "name": "Anime 2018 <?php echo $environment->getName(); ?>",
    "short_name": "<?php echo $environment->getShortName(); ?>",

    "start_url": "/",
    "display": "standalone",
    "orientation": "portrait",

    "theme_color": "#01579B",

    "icons": [
        {
            "src": "images/logo-256.png",
            "type": "image/png",
            "sizes": "256x256",
            "density": 1
        },
        {
            "src": "images/logo-192-2.png",
            "type": "image/png",
            "sizes": "192x192",
            "density": 2
        },
        {
            "src": "images/logo-192.png",
            "type": "image/png",
            "sizes": "192x192",
            "density": 1
        },
        {
            "src": "images/logo-128.png",
            "type": "image/png",
            "sizes": "128x128",
            "density": 1
        }
    ]
}
