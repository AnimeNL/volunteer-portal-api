<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// The GoogleClient class encapsulates authentication with the Google Cloud API, with a slightly
// higher level API that hides some of our own file structure.
class GoogleClient {
    // File in which the authentication configuration is stored.
    const AUTH_CONFIG_FILE = __DIR__ . '/../../configuration/credentials.json';

    // File in which the access and refresh tokens will be stored.
    const AUTH_TOKEN_FILE = __DIR__ . '/../../configuration/credentials-token.json';

    // The client instance, potentially unauthenticated.
    private $client;

    // Whether the client has been authenticated, done on first use.
    private $didAuthenticate = false;

    public function __construct() {
        $this->client = new \Google_Client();
        $this->client->setAccessType('offline');
        $this->client->setApplicationName('Anime Volunteer Portal');
        $this->client->setAuthConfig(self::AUTH_CONFIG_FILE);
        $this->client->setPrompt('select_account consent');

        // Update this if any additional scopes are necessary.
        $this->client->setScopes(\Google_Service_Sheets::SPREADSHEETS);
    }

    // Authenticates, and refreshes the token if required. 
    private function authenticate() : void {
        if (file_exists(self::AUTH_TOKEN_FILE)) {
            $this->client->setAccessToken(
                json_decode(file_get_contents(self::AUTH_TOKEN_FILE), true));
        }

        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = $this->client->getRefreshToken();

            if (!is_null($refreshToken)) {
                $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            } else if (php_sapi_name() == 'cli') {
                // Manual authentication is necessary. This cannot be done whilst in the browser, so
                // only allow the following steps on the CLI.
                $authenticationUrl = $this->client->createAuthUrl();

                echo 'Open the following link in your browser: ' . PHP_EOL . $authenticationUrl;
                echo PHP_EOL . PHP_EOL;

                echo 'Enter the verification code: ';

                $authenticationCode = trim(fgets(STDIN));
                $accessToken = $this->client->fetchAccessTokenWithAuthCode($authenticationCode);

                if (array_key_exists('error', $accessToken))
                    throw new Exception(implode(', ', $accessToken));
                
                $this->client->setAccessToken($accessToken);
            }

            if (!$this->client->isAccessTokenExpired()) {
                $accessToken = $this->client->getAccessToken();
                if (!array_key_exists('refresh_token', $accessToken) && !is_null($refreshToken))
                    $accessToken['refresh_token'] = $refreshToken;

                file_put_contents(self::AUTH_TOKEN_FILE, json_encode($accessToken));
            }
        }

        $this->didAuthenticate = true;
    }

    // Returns the initialized and authenticated Google_Client, or throws if that can't be done.
    public function getClient() : \Google_Client {
        if (!$this->didAuthenticate)
            $this->authenticate();

        if ($this->client->isAccessTokenExpired())
            throw new \Exception('Unable to issue a client with a valid access token.');
        
        return $this->client;
    }

}
