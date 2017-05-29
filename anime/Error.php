<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

function dieWithError($error) {
    die(json_encode([ 'error' => $error ]));
}

// Adds the |$message| to the log file for notification subscriptions. This enables us to keep a
// little bit of insight in what's happening.

function logMessage($message) {
    $line = date('[Y-m-d H:i:s] ') . $_SERVER['REMOTE_ADDR'] . ' ' . trim($message) . PHP_EOL;
    file_put_contents(__DIR__ . '/notifications.log', $line, FILE_APPEND);
}
