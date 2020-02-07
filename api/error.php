<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

function dieWithError($error) {
    die(json_encode([ 'success' => false, 'error' => $error ]));
}

function dieWithMessage($message) {
    die(json_encode([ 'success' => false, 'error' => 'user issue', 'message' => $message ]));
}
