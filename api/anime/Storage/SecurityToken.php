<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// The SecurityToken class encapsulates the ability to generate user tokens and auth tokens based
// on the e-mail address and access code of a volunteer.
//
// * User tokens are unique identifiers for a particular volunteer, and may be shared with other
//   volunteers.
//
// * Auth tokens are private authentication identifiers for a particular volunteer, and must remain
//   private to that volunteer.
class SecurityToken {
    // Generates an eight character user token based on the given |$accessCode| and |$emailAddress|.
    public static function GenerateUserToken(string $accessCode, ?string $emailAddress) : string {
        $configuration = \Anime\Configuration::getInstance();

        $data = $accessCode . $emailAddress;
        $salt = $configuration->get('authentication/userTokenSalt');

        return substr(base_convert(hash('fnv164', $data . $salt), 16, 32), 0, 8);
    }

    // Generates an eight character auth token based on the given |$accessCode| and |$emailAddress|.
    public static function GenerateAuthToken(string $accessCode, ?string $emailAddress) : string {
        $configuration = \Anime\Configuration::getInstance();

        $data = $accessCode . $emailAddress;
        $salt = $configuration->get('authentication/authTokenSalt');

        return substr(base_convert(hash('fnv164', $data . $salt), 16, 32), 0, 8);
    }
}
