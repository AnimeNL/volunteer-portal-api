<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

// Salt that should be considered when creating the access code hash.
const ACCESS_CODE_SALT = 'BrTHWT7nLe3uAqQRDGRdepEs';

// Length, in number of characters, for the to-be-generated access code.
const ACCESS_CODE_LENGTH = 4;

// Generates an access code for |$name| by running it through a hashing function and selecting a
// certain number of characters from it. A salt for the generation can be configured in the
// service's configuration section.
//
// These access codes are required for all volunteers in order to access the application.
function generateAccessCode($name) : string {
    $phrase = base_convert(hash('fnv164', $name . ACCESS_CODE_SALT), 16, 10);
    return strtoupper(substr($phrase, 0, ACCESS_CODE_LENGTH));
}
