<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

if (!file_exists(__DIR__ . '/schedule-private.php'))
    exit;

require __DIR__ . '/schedule-private.php';

// -------------------------------------------------------------------------------------------------

// (1) This must be a GitHub PUSH message, indicating that a repository changed.
if (!isset ($_SERVER['HTTP_X_GITHUB_EVENT']))
    exit;

if ($_SERVER['HTTP_X_GITHUB_EVENT'] != 'push')
    exit;

// (2) This must be a GitHub request, with the entire contents signed.
if (!isset ($_SERVER['HTTP_X_HUB_SIGNATURE']))
    exit;

$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'];
if (strpos($signature, '=') == false)
    exit;

list($algorithm, $hash) = explode('=', $signature, 2);

$payload = file_get_contents('php://input');
if (hash_hmac($algorithm, $payload, SECRET) != $hash)
    exit;

// -------------------------------------------------------------------------------------------------

$commands = [
    // Updates the local copy of the repository with the most recent remote changes.
    'git fetch --all',

    // Resets the repository to the state the remote currently is in.
    'git reset --hard origin/master',

    // Remove any left-over files that are not part of the checkout, and not in .gitignore.
    'git clean -f -d',

    // Write the latest commit SHA to the VERSION file.
    'git rev-parse HEAD > VERSION'
];

$directory = realpath(__DIR__ . '/../../');
foreach ($commands as $command)
    echo shell_exec('cd ' . $directory . ' && ' . $command);
