<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Storage;

class SecurityTokenTest extends \PHPUnit\Framework\TestCase {
    // Verifies that user and auth tokens are eight characters.
    public function testTokenSerialization() {
        $accessCode = '8491';
        $emailAddress = 'user@example.com';

        $userToken = SecurityToken::GenerateUserToken($accessCode, $emailAddress);
        $authToken = SecurityToken::GenerateAuthToken($accessCode, $emailAddress);

        $this->assertTrue(!!preg_match('/^[a-z0-9]+$/', $userToken));
        $this->assertTrue(!!preg_match('/^[a-z0-9]+$/', $authToken));
    }

    // Verifies that user and auth tokens are deterministic given the same input values.
    public function testTokenDeterminism() {
        $accessCode = '8491';
        $emailAddress = 'user@example.com';

        $this->assertEquals(SecurityToken::GenerateUserToken($accessCode, $emailAddress),
                            SecurityToken::GenerateUserToken($accessCode, $emailAddress));

        $this->assertEquals(SecurityToken::GenerateAuthToken($accessCode, $emailAddress),
                            SecurityToken::GenerateAuthToken($accessCode, $emailAddress));
    }

    // Verifies that user and auth tokens generate different values given the same input values.
    public function testTokenSeparation() {
        $accessCode = '8491';
        $emailAddress = 'user@example.com';

        $this->assertNotEquals(SecurityToken::GenerateUserToken($accessCode, $emailAddress),
                               SecurityToken::GenerateAuthToken($accessCode, $emailAddress));
    }

    // Verifies that user and auth tokens are different for different input values.
    public function testTokenDifferentiation() {
        $this->assertNotEquals(SecurityToken::GenerateUserToken('1234', 'user@example.com'),
                               SecurityToken::GenerateUserToken('5678', 'user@example.com'));

        $this->assertNotEquals(SecurityToken::GenerateUserToken('1234', 'user@example.com'),
                               SecurityToken::GenerateUserToken('1234', 'user@hostname.com'));

        $this->assertNotEquals(SecurityToken::GenerateAuthToken('1234', 'user@example.com'),
                               SecurityToken::GenerateAuthToken('5678', 'user@example.com'));

        $this->assertNotEquals(SecurityToken::GenerateAuthToken('1234', 'user@example.com'),
                               SecurityToken::GenerateAuthToken('1234', 'user@hostname.com'));
    }
}
