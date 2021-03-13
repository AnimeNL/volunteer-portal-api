<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require __DIR__ . '/../vendor/autoload.php';

$googleClient = new \Anime\Storage\Backend\GoogleClient();
$client = $googleClient->getClient();

echo 'Client ID: ' . $client->getClientId() . PHP_EOL;
echo 'Expired? ' . ($client->isAccessTokenExpired() ? 'Yes' : 'No') . PHP_EOL;
