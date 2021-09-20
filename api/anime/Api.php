<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

use \Anime\Storage\RegistrationDatabase;
use \Anime\Storage\RegistrationDatabaseFactory;

// Implementation of the actual API calls as methods whose input has been validated for syntax, and
// for whom the appropriate environment is already available.
class Api {
    // Directory and request path in which avatar information has been stored.
    private const AVATAR_DIRECTORY = __DIR__ . '/../../avatars/';
    private const AVATAR_PATH = '/avatars/';

    private Cache $cache;
    private Configuration $configuration;
    private Environment $environment;

    public function __construct(string $hostname) {
        $this->cache = Cache::getInstance();
        $this->configuration = Configuration::getInstance();
        $this->environment = EnvironmentFactory::createForHostname($this->configuration, $hostname);

        if (!$this->environment->isValid())
            throw new \Exception('The "' . $hostname . '" is not known as a valid environment.');
    }

    /**
     * Allows an application to be submitted to the system, for a particular event. We'll try to
     * merge applications with previous events when sufficient information is available. All the
     * parameters have been syntactically validated prior to hitting this code.
     *
     * @param event Identifier of the event that the volunteer is applying for.
     * @param firstName First name of the volunteer that's applying.
     * @param lastName Last name of the volunteer that's applying.
     * @param dateOfBirth Date of birth (YYYY-MM-DD) of that volunteer.
     * @param emailAddress The e-mail address at which they can be contacted.
     * @param phoneNumber Their phone number, only for use by the leadership.
     * @param gender Their self-indicated gender, if any.
     * @param shirtSize Their self-indicated t-shirt size, if any.
     * @param preferences Their preferences for volunteering, if any.
     * @param available Whether they are available during the entire festival.
     * @param hotel Whether they would like to reserve a hotel room through our booking.
     * @param whatsApp Whether they would like to be part of the WhatsApp group.
     * @see https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apiapplication
     */
    public function application(string $event, string $firstName, string $lastName,
                                string $dateOfBirth, string $emailAddress, string $phoneNumber,
                                string $gender, string $shirtSize, string $preferences,
                                bool $available, bool $hotel, bool $whatsApp) {
        $database = $this->getRegistrationDatabase(/* writable= */ true);
        if ($database) {
            if (!$database->isValidEvent($event))
                return [ 'error' => 'The event "' . $event . '" is not known to the database.' ];

            $registrations = $database->getRegistrations();
            $registration = null;

            foreach ($registrations as $candidate) {
                if (strtolower($candidate->getEmailAddress()) !== strtolower($emailAddress))
                    continue;  // non-matching e-mail address

                if ($candidate->getDateOfBirth() !== $dateOfBirth)
                    continue;  // non-matching date of birth

                $events = $candidate->getEvents();
                if (array_key_exists($event, $events) && $events[$event]['role'] !== 'Unregistered') {
                    return [
                        'error' => 'You already have been registered for this event, and cannot ' .
                                           'register again! Reach out to a senior if this is wrong.'
                    ];
                }

                $registration = $database->createApplication($candidate, $event);
                break;
            }

            if (!$registration) {
                $registration = $database->createRegistration(
                        $event, $firstName, $lastName, $gender, $dateOfBirth, $emailAddress,
                        $phoneNumber);
            }

            $message = new \Nette\Mail\Message;
            $message->setFrom('anime@' . $this->environment->getHostname());
            $message->addTo($this->environment->getApplicationAddress());

            $message->setSubject('Volunteer application: ' . $firstName . ' ' . $lastName);
            $message->setHtmlBody(ApplicationMessageGenerator::Generate(
                $registration, $event, $firstName, $lastName, $dateOfBirth, $emailAddress,
                $phoneNumber, $gender, $shirtSize, $preferences, $available, $hotel, $whatsApp));

            $mailer = new \Nette\Mail\SendmailMailer;
            $mailer->send($message);

            return [
                'accessCode'    => $registration->getAccessCode(),
            ];
        }

        return [
            'error'         => 'The database was not available...',
        ];
    }

    /**
     * Allows an authentication token (authToken) to be obtained for given credentials. The token
     * may have an expiration time, which should be validated on both the client and server-side.
     *
     * @param emailAddress The e-mail address associated to authenticate with.
     * @param accessCode Access code given to the person who owns this e-mail address.
     * @see https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apiauth
     */
    public function auth(string $emailAddress, string $accessCode) {
        $database = $this->getRegistrationDatabase(/* writable= */ false);
        if ($database) {
            $registrations = $database->getRegistrations();

            foreach ($registrations as $registration) {
                if ($registration->getEmailAddress() !== $emailAddress)
                    continue;  // non-matching e-mail address

                if ($registration->getAccessCode() !== $accessCode)
                    continue;  // non-matching access code

                return [
                    'authToken'             => $registration->getAuthToken(),
                    'authTokenExpiration'   => time() + /* 30 minutes= */ 1800,
                ];
            }
        }

        return [ /* invalid credentials */ ];
    }

    /**
     * Allows static content to be obtained for the registration sub-application, as well as other
     * pages that can be displayed on the portal. The <App> component is responsible for routing.
     *
     * @see https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apicontent
     */
    public function content() {
        return [
            'pages' => $this->environment->getContent(),
        ];
    }

    /**
     * Allows information to be obtained for the environment the volunteer portal runs under. This
     * allows multiple events to be managed by the same instance.
     *
     * @see https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apienvironment
     */
    public function environment(): array {
        $events = [];

        foreach ($this->environment->getEvents() as $event) {
            $events[] = [
                'name'                  => $event->getName(),
                'enableContent'         => $event->enableContent(),
                'enableRegistration'    => $event->enableRegistration(),
                'enableSchedule'        => $event->enableSchedule(),
                'identifier'            => $event->getIdentifier(),
                'timezone'              => $event->getTimezone(),
                'website'               => $event->getWebsite() ?? '',
            ];
        }

        return [
            'contactName'   => $this->environment->getContactName(),
            'contactTarget' => $this->environment->getContactTarget(),
            'events'        => $events,
            'title'         => $this->environment->getTitle(),
        ];
    }

    /**
     * Allows full scheduling information to be requested about a particular event, indicated by the
     * `event` request parameter. The returned data is expected to have been (pre)filtered based on
     * the access level granted to the owner of the given `authToken`.
     *
     * @param authToken The authentication token that was issued to this user.
     * @param eventIdentifier The unique identifier for the event whose program to retrieve.
     * @see https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apievent
     */
    public function event(string $authToken, string $eventIdentifier): array {
        return [
            'events'        => [],
            'locations'     => [],
            'volunteers'    => [],
        ];
    }

    /**
     * Allows information about the authenticated user to be obtained, both for verification of
     * validity of the authentication token, as for appropriate display of their information in
     * the user interface.
     *
     * @param authToken The authentication token that was issued to this user.
     * @see https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apiuser
     */
    public function user(string $authToken) {
        $database = $this->getRegistrationDatabase(/* writable= */ false);
        $events = array_map(fn($event) => $event->getIdentifier(), $this->environment->getEvents());

        if ($database) {
            $registrations = $database->getRegistrations();

            foreach ($registrations as $registration) {
                if ($registration->getAuthToken() !== $authToken)
                    continue;  // non-matching authentication token

                $avatarFile = $registration->getUserToken() . '.jpg';
                $avatarPath = self::AVATAR_PATH . $avatarFile;

                $avatarUrl = '';  // no avatar specified

                if (file_exists(self::AVATAR_DIRECTORY . $avatarFile))
                    $avatarUrl = 'https://' . $this->environment->getHostname() . $avatarPath;

                $composedName = $registration->getFirstName() . ' ' . $registration->getLastName();
                $filteredEvents = [];

                foreach ($registration->getEvents() as $eventIdentifier => $participationData) {
                    if (in_array($eventIdentifier, $events))
                        $filteredEvents[$eventIdentifier] = $participationData['role'];
                }

                return [
                    'administrator' => $registration->isAdministrator(),
                    'avatar'        => $avatarUrl,
                    'events'        => $filteredEvents,
                    'name'          => trim($composedName),
                ];
            }
        }

        return [ /* invalid auth token */ ];
    }

    // ---------------------------------------------------------------------------------------------

    // Returns the RegistrationDatabase instance for the current environment. Immutable by default
    // unless the |$writable| argument has been set to TRUE.
    private function getRegistrationDatabase(bool $writable = false): ?RegistrationDatabase {
        $settings = $this->environment->getRegistrationDatabaseSettings();
        if (!is_array($settings))
            return null;  // no data has been specified for this environment

        if (!array_key_exists('spreadsheet', $settings) || !array_key_exists('sheet', $settings))
            return null;  // invalid data has been specified for this environment

        $spreadsheetId = $settings['spreadsheet'];
        $sheet = $settings['sheet'];

        if ($writable)
            return RegistrationDatabaseFactory::openReadWrite($this->cache, $spreadsheetId, $sheet);
        else
            return RegistrationDatabaseFactory::openReadOnly($this->cache, $spreadsheetId, $sheet);
    }
}
