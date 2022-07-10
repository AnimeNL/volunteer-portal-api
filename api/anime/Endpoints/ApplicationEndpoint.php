<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\ApplicationMessageGenerator;
use \Anime\Endpoint;

// Helper function to validate whether a POST value is a stringified boolean.
function isBool(string $value): bool {
    return $value === 'true' || $value === 'false';
}

// Helper function to translate a stringified boolean to an actual boolean.
function toBool(string $value): bool {
    if (!isBool($value))
        throw new Error('Invalid boolean values may not be converted: ' . $value);

    return $value === 'true';
}

// Allows an application to be submitted to the system, for a particular event. We'll try to
// merge applications with previous events when sufficient information is available. All the
// parameters have been syntactically validated prior to hitting this code.
//
// See https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apiapplication
class ApplicationEndpoint implements Endpoint {
    public function validateInput(array $requestParameters, array $requestData): bool | string {
        if (!array_key_exists('event', $requestParameters) || !strlen($requestParameters['event']))
            return 'No event to register for has been supplied.';

        if (!array_key_exists('firstName', $requestData) || !strlen($requestData['firstName']))
            return 'Please enter your first name.';

        if (!array_key_exists('lastName', $requestData) || !strlen($requestData['lastName']))
            return 'Please enter your last name.';

        if (!array_key_exists('dateOfBirth', $requestData) ||
                       !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $requestData['dateOfBirth'])) {
            return 'Please enter a valid date of birth (YYYY-MM-DD).';
        }

        if (!array_key_exists('emailAddress', $requestData) ||
                       !filter_var($requestData['emailAddress'], FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid e-mail address.';
        }

        if (!array_key_exists('phoneNumber', $requestData) || !strlen($requestData['phoneNumber']))
            return 'Please enter your phone number.';

        if (!array_key_exists('gender', $requestData) || !strlen($requestData['gender']))
            return 'Please enter your preferred gender.';

        if (!array_key_exists('shirtSize', $requestData) || !strlen($requestData['shirtSize']))
            return 'Please enter your preferred t-shirt size.';

        if (!array_key_exists('commitmentHours', $requestData) || !strlen($requestData['commitmentHours']))
            return 'Please enter your preferred hours of commitment.';

        if (!array_key_exists('commitmentTiming', $requestData) || !strlen($requestData['commitmentTiming']))
            return 'Please enter your preferred timing of commitment.';

        if (!array_key_exists('preferences', $requestData))
            return 'Please enter your volunteering preferences.';

        if (!array_key_exists('available', $requestData) || !isBool($requestData['available']))
            return 'Please enter whether you are available.';

        if (!array_key_exists('credits', $requestData) || !isBool($requestData['credits']))
            return 'Please enter whether you would like to be included in the credit reel.';

        if (!array_key_exists('hotel', $requestData) || !isBool($requestData['hotel']))
            return 'Please enter whether you would like a hotel.';

        if (!array_key_exists('whatsApp', $requestData) || !isBool($requestData['whatsApp']))
            return 'Please indicate whether to join the WhatsApp group.';

        if (!array_key_exists('covidRequirements', $requestData) || !$requestData['covidRequirements'])
            return 'You must agree with the COVID-19 requirements.';

        if (!array_key_exists('gdprRequirements', $requestData) || !$requestData['gdprRequirements'])
            return 'You must agree with the GDPR requirements.';

        return true;
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        $database = $api->getRegistrationDatabase(/* writable= */ true);
        $environment = $api->getEnvironment();

        $event = $requestParameters['event'];

        $firstName = $requestData['firstName'];
        $lastName = $requestData['lastName'];
        $dateOfBirth = $requestData['dateOfBirth'];
        $emailAddress = $requestData['emailAddress'];
        $phoneNumber = $requestData['phoneNumber'];
        $gender = $requestData['gender'];
        $shirtSize = $requestData['shirtSize'];
        $commitmentHours = $requestData['commitmentHours'];
        $commitmentTiming = $requestData['commitmentTiming'];
        $preferences = $requestData['preferences'];
        $available = toBool($requestData['available']);
        $credits = toBool($requestData['credits']);
        $hotel = toBool($requestData['hotel']);
        $whatsApp = toBool($requestData['whatsApp']);

        if ($database) {
            if (!$database->isValidEvent($event))
                return [ 'error' => 'The event "' . $event . '" is not known to the database.' ];

            $registrations = $database->getRegistrations();
            $registration = null;

            foreach ($registrations as $candidate) {
                if ($candidate->hasEmailAddress() &&
                        strtolower($candidate->getEmailAddress()) !== strtolower($emailAddress)) {
                    continue;  // non-matching e-mail address
                }

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
            $message->setFrom('anime@' . $environment->getHostname());
            $message->addTo($environment->getApplicationAddress());

            $message->setSubject('[' . $event . '] Volunteer application: ' . $firstName . ' ' . $lastName);
            $message->setHtmlBody(ApplicationMessageGenerator::Generate(
                $registration, $event, $firstName, $lastName, $dateOfBirth, $emailAddress,
                $phoneNumber, $gender, $shirtSize, $commitmentHours, $commitmentTiming,
                $preferences, $available, $credits, $hotel, $whatsApp));

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
}
