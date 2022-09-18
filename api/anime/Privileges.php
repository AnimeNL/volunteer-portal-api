<?php
// Copyright 2022 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime;

use \Anime\Storage\Model\Registration;

// Decides on the privileges that should be granted to a particular volunteer during a particular
// event, which decides what they're able to do and access in the volunteer portal.
class Privileges {
    private const PRIVILEGE_NONE = 0;
    private const PRIVILEGE_ALL = PHP_INT_MAX;

    // Privileges that can be assigned to individuals based on their access level, role, team and
    // whether or not they've been marked as an administrator.
    public const PRIVILEGE_ACCESS_CODES_ANY = 1;
    public const PRIVILEGE_ACCESS_CODES_ENVIRONMENT = 2;
    public const PRIVILEGE_PHONE_NUMBERS_ANY = 4;
    public const PRIVILEGE_PHONE_NUMBERS_ENVIRONMENT = 8;
    public const PRIVILEGE_PHONE_NUMBERS_SENIORS = 16;
    public const PRIVILEGE_UPDATE_AVATAR_ANY = 32;
    public const PRIVILEGE_UPDATE_AVATAR_ENVIRONMENT = 64;
    public const PRIVILEGE_UPDATE_EVENT_NOTES = 128;
    public const PRIVILEGE_USER_NOTES_ANY = 256;
    public const PRIVILEGE_USER_NOTES_ENVIRONMENT = 512;
    public const PRIVILEGE_NARDO = 1024;

    // List of hosts and which environments they have access to. This is to enable hosts and
    // stewards to see each other, as their shifts will intertwine in a number of cases.
    private const CROSS_ENVIRONMENT_ACCESS_LIST = [
        'hosts.team'    => [ 'hosts.team', 'stewards.team' ],
        'stewards.team' => [ 'hosts.team', 'stewards.team' ],
    ];

    // List of all environments that are enabled through this endpoint at the moment.
    public const CROSS_ENVIRONMENT_LIST = [ 'gophers.team', 'hosts.team', 'stewards.team' ];

    // Allows construction of a Privileges instance for the given |$registration| and |$event|.
    static function forRegistration(Environment $environment,
                                    Registration $registration,
                                    string $event): Privileges {
        if ($registration->isAdministrator())
            return new self($environment, self::CROSS_ENVIRONMENT_LIST, self::PRIVILEGE_ALL);

        $environments = [];
        $privileges = self::PRIVILEGE_NONE;

        // Always allow volunteers to see the phone numbers of senior volunteers.
        $privileges |= self::PRIVILEGE_PHONE_NUMBERS_SENIORS;

        // Access to phone numbers and access codes is limited to staff and senior volunteers, where
        // the distinction between the levels influences multi-environment access.
        $role = $registration->getEventAcceptedRole($event);

        $isStaff = stripos($role, 'Staff') !== false;
        $isSenior = stripos($role, 'Senior') !== false;

        if ($isStaff || $isSenior) {
            $environments = self::CROSS_ENVIRONMENT_LIST;

            $privileges |= self::PRIVILEGE_PHONE_NUMBERS_ANY;
            $privileges |= self::PRIVILEGE_PHONE_NUMBERS_ENVIRONMENT;

            $privileges |= self::PRIVILEGE_UPDATE_EVENT_NOTES;

            $privileges |= self::PRIVILEGE_ACCESS_CODES_ENVIRONMENT;
            $privileges |= self::PRIVILEGE_UPDATE_AVATAR_ENVIRONMENT;
            $privileges |= self::PRIVILEGE_USER_NOTES_ENVIRONMENT;

            if ($isStaff) {
                $privileges |= self::ACCESS_CODES_ANY;
                $privileges |= self::PRIVILEGE_UPDATE_AVATAR_ANY;
                $privileges |= self::PRIVILEGE_USER_NOTES_ANY;
            }
        } else {
            $environmentHostname = $environment->getHostname();
            if (array_key_exists($environmentHostname, self::CROSS_ENVIRONMENT_ACCESS_LIST))
                $environments = self::CROSS_ENVIRONMENT_ACCESS_LIST[$environmentHostname];
            else
                $environments = [ $environmentHostname ];
        }

        // Support queries will be available to company representatives after 16:30pm on Friday,
        // June 10th, 2022. Administrators will be able to access queries prior to that.
        if ($registration->getUserToken() === '41981vim' && time() >= 1654875000)
            $privileges |= self::PRIVILEGE_NARDO;

        return new self($environment, $environments, $privileges);
    }

    private Environment $environment;
    private array $environments;
    private int $privileges;

    private function __construct(Environment $environment, array $environments, int $privileges) {
        $this->environment = $environment;
        $this->environments = $environments;
        $this->privileges = $privileges;
    }

    // Whether the given |$privilege| has been granted.
    public function can(int $privilege): bool {
        return $this->privileges & $privilege;
    }

    // Returns a list of environments that can be accessed.
    public function getEnvironments(): array {
        return $this->environments;
    }

    // Returns whether the given |$environment| is the one native to the |$registration|. Works
    // across multiple instances of Environment objects by comparing the underlying hostnames.
    public function isOwnEnvironment(Environment | string $environment): bool {
        if (is_string($environment))
            return $this->environment->getHostname() === $environment;
        else
            return $this->environment->getHostname() == $environment->getHostname();
    }
}
