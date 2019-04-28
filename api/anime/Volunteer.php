<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// Represents a volunteer of the convention, containing all information known about that volunteer.
// This object is usually owned and created by the VolunteerList class.
class Volunteer {
    // The recognized types of volunteers.
    public const TYPE_VOLUNTEER = 'Volunteer';
    public const TYPE_SENIOR = 'Senior';
    public const TYPE_STAFF = 'Staff';

    // Directory in which hashed photos of the volunteers are stored.
    private const PHOTO_DIRECTORY = __DIR__ . '/../images/photos/';

    // Full name of the volunteer.
    private $name;

    // The randomly access_code for the volunteer.
    private $accessCode;

    // Tokens unique to this volunteer. One for identification purposes, one for authentication ones
    private $authToken;
    private $userToken;

    // Type of volunteer, must be one of the constants defined earlier in this class.
    private $type;

    // E-mail address of the volunteer. Must validate.
    private $email;

    // Telephone number of the volunteer.
    private $telephone;

    // Whether the volunteer is an administrator.
    private $isAdmin;

    // Whether the volunteer should have debugging information enabled.
    private $isDebug;

    // Constructs a new Volunteer object based on |$volunteerData|. The input data will be checked
    // for correctness to ensure the instance is valid.
    public function __construct($volunteerData) {
        if (!array_key_exists('name', $volunteerData) || !is_string($volunteerData['name']))
            throw new \TypeError('The volunteer\'s `name` is expected to be a string.');

        $this->name = $volunteerData['name'];

        if (!array_key_exists('access_code', $volunteerData) ||
            !is_string($volunteerData['access_code'])) {
            throw new \TypeError('The volunteer\'s `access_code` is expected to be a string.');
        }

        $this->accessCode = $volunteerData['access_code'];

        if (!array_key_exists('type', $volunteerData) || !is_string($volunteerData['type']))
            throw new \TypeError('The volunteer\'s `type` is expected to be a string.');

        if (!in_array($volunteerData['type'], [ self::TYPE_VOLUNTEER, self::TYPE_SENIOR,
                                                self::TYPE_STAFF ])) {
            throw new \TypeError('The volunteer\'s `type` has got an invalid value.');
        }

        $this->type = $volunteerData['type'];

        if (!array_key_exists('email', $volunteerData) || !is_string($volunteerData['email']))
            throw new \TypeError('The volunteer\'s `email` is expected to be a string.');

        if (!filter_var($volunteerData['email'], FILTER_VALIDATE_EMAIL))
            throw new \TypeError('The volunteer\'s `email` must be a valid e-mail address.');

        $this->email = $volunteerData['email'];

        // Calculate the unique token and authentication token for this volunteer. One is public,
        // one is private, but other than that they're surprisingly similar.
        {
            $config = Configuration::getInstance();

            $this->authToken = $this->generateHash($this->name, $config->get('authTokenSalt'));
            $this->userToken = $this->generateHash($this->name, $config->get('userTokenSalt'));
        }

        if (!array_key_exists('telephone', $volunteerData) ||
            !is_string($volunteerData['telephone'])) {
            throw new \TypeError('The volunteer\'s `telephone` is expected to be a string.');
        }

        $this->telephone = preg_replace('/\s|\(\d\)/s', '', $volunteerData['telephone']);

        if (!array_key_exists('is_admin', $volunteerData) || !is_bool($volunteerData['is_admin']))
            throw new \TypeError('The volunteer\'s `is_admin` is expected to be a boolean.');

        $this->isAdmin = $volunteerData['is_admin'];

        if (!array_key_exists('is_debug', $volunteerData) || !is_bool($volunteerData['is_debug']))
            throw new \TypeError('The volunteer\'s `is_debug` is expected to be a boolean.');

        $this->isDebug = $volunteerData['is_debug'];
    }

    // Returns the volunteer's full name as a string.
    public function getName() : string {
        return $this->name;
    }

    // Return the volunteer's generated access code as a string.
    public function getAccessCode() : string {
        return $this->accessCode;
    }

    // Returns the authentication token associated with this volunteer.
    public function getAuthToken() : string {
        return $this->authToken;
    }

    // Returns the identification token associated with this volunteer.
    public function getUserToken() : string {
        return $this->userToken;
    }

    // Returns whether this volunteer is a senior member of the volunteers.
    public function isSeniorVolunteer() : bool {
        return $this->type === Volunteer::TYPE_SENIOR ||
               $this->type === Volunteer::TYPE_STAFF;
    }

    // Returns the type { 'Volunteer', 'Senior', 'Staff' } of this volunteer.
    public function getType() : string {
        return $this->type;
    }

    // Returns a relative URL to a photo representing this volunteer. If a PNG file named by the
    // crc32 hash of their name exists in the /images/photos/ directory it will be used, otherwise
    // the /images/no-photo.png file will be returned.
    public function getPhoto() : string {
        $hash = crc32($this->name);
        $filename = self::PHOTO_DIRECTORY . $hash . '.png';

        if (file_exists($filename))
            return '/images/photos/' . $hash . '.png';

        return '/images/no-photo.png';
    }

    // Returns the e-mail address of this volunteer.
    public function getEmail() : string {
        return $this->email;
    }

    // Returns the telephone number of this volunteer.
    public function getTelephone() : string {
        return $this->telephone;
    }

    // Returns whether this volunteer is an administrator.
    public function isAdmin() : bool {
        return $this->isAdmin;
    }

    // Returns whether this volunteer should have debugging rights.
    public function isDebug() : bool {
        return $this->isDebug;
    }

    // Calculates a hash for the given |$data| with the given $salt. The returned string will be
    // eight characters in length and stable for the input data.
    private function generateHash($data, $hash) : string {
        $phrase = base_convert(hash('fnv164', $data . $hash), 16, 32);
        return substr($phrase, 0, 8);
    }
}
