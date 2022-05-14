<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Cache;
use \Anime\Endpoint;
use \Anime\EnvironmentFactory;
use \Anime\Event;
use \Anime\Storage\Model\Registration;
use \Anime\Storage\NotesDatabase;
use \Anime\Storage\RegistrationDatabase;
use \Anime\Storage\ScheduleDatabaseFactory;

// Comperator for sorting IEventResponse{Area,Location} structures in ascending order by name.
function CreateAreaOrLocationComparator() {
    return fn($lhs, $rhs) => strcmp($lhs['name'], $rhs['name']);
}

// Comparator for sorting IEventResponseEvent structures in ascending order by starting time.
function CreateEventComparator() {
    return function($lhs, $rhs) {
        $result = $lhs['sessions'][0]['time'][0] - $rhs['sessions'][0]['time'][0];
        if (!$result)
            return $lhs['sessions'][0]['time'][1] - $rhs['sessions'][0]['time'][1];

        return $result;
    };
}

// Comparator for sorting IEventResponseVolunteer structures in ascending order by name.
function CreateVolunteerComparator() {
    return function($lhs, $rhs) {
        $result = strcmp($lhs['name'][0], $rhs['name'][0]);
        if (!$result)
            return strcmp($lhs['name'][1], $rhs['name'][1]);

        return $result;
    };
}

// Allows full scheduling information to be requested about a particular event, indicated by the
// `event` request parameter. The returned data is expected to have been (pre)filtered based on
// the access level granted to the owner of the given `authToken`.
//
// See https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apievent
class EventEndpoint implements Endpoint {
    private const PRIVILEGE_NONE = 0;
    private const PRIVILEGE_ALL = PHP_INT_MAX;

    // Privileges that can be assigned to individuals based on their access level, role, team and
    // whether or not they've been marked as an administrator.
    private const PRIVILEGE_ACCESS_CODES = 1;
    private const PRIVILEGE_CROSS_ENVIRONMENT = 2;
    private const PRIVILEGE_PHONE_NUMBERS = 4;
    private const PRIVILEGE_USER_NOTES = 8;

    // List of hosts whose seniors have the ability to access volunteers of the other environments.
    // Shared with the AvatarEndpoint.
    public const CROSS_ENVIRONMENT_HOSTS_ALLOWLIST = [ 'stewards.team '];

    // Salt used for hashing the location identifiers.
    private const LOCATION_SALT = '3hhmgPw4';

    // The list of environments that the requesting user has access to.
    private array $environments;

    // The Event instance in the portal's primary environment for this request.
    private Event | null $environmentEvent;

    // The privileges associated with the user who's requesting the event information.
    private int $privileges;

    // The registration associated with the requesting user, if any.
    private Registration | null $registration;

    // Cache of the location IDs that have already been assigned.
    private array $locationCache;

    // Array of all notes that are available for this event.
    private array $notes;

    public function __construct() {
        $this->environments = [ /* empty by default */ ];
        $this->environmentEvent = null;
        $this->privileges = self::PRIVILEGE_NONE;
        $this->locationCache = [];
    }

    public function validateInput(array $requestParameters, array $requestData): bool | string {
        if (!array_key_exists('authToken', $requestParameters))
            return 'Missing parameter: authToken';

        if (!array_key_exists('event', $requestParameters))
            return 'Missing parameter: event';

        return true;
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        $authToken = $requestParameters['authToken'];
        $event = $requestParameters['event'];

        // Authenticates the requesting user based on the request parameters, and populates the
        // |$environments|, |$privileges| and |$registration| class member variables.
        if (!$this->authenticateRequestUser($api, $authToken, $event))
            return [];

        $this->notes = NotesDatabase::create($event)->all();

        $events = $this->populateEvents($event);
        $volunteers = $this->populateVolunteers($event);
        $areas = $this->populateAreas($event);
        $locations = $this->populateLocations();

        // Shifts have the ability to modify the list of events, areas and locations, so have to
        // be processed after each of the other entities are known.
        $shifts = $this->populateShifts($event, $api->getCache(), $areas, $events, $locations);

        // Sort each of the output arrays, and remove the associative keying since they should be
        // returned as lists, rather than indexed structures.
        usort($areas, CreateAreaOrLocationComparator());
        usort($events, CreateEventComparator());
        usort($locations, CreateAreaOrLocationComparator());
        usort($volunteers, CreateVolunteerComparator());

        return [
            'meta'          => [
                'name'      => $this->environmentEvent?->getName(),
                'timezone'  => $this->environmentEvent?->getTimezone(),
            ],

            'areas'         => $areas,
            'events'        => $events,
            'locations'     => $locations,
            'shifts'        => $shifts,
            'volunteers'    => $volunteers,
        ];
    }

    // Authenticates the user's authentication token and event information against the registration
    // database stored in the |$api|. The user must be an active participant within the given event
    // in order to be granted access.
    private function authenticateRequestUser(Api $api, string $authToken, string $event): bool {
        $currentEnvironment = $api->getEnvironment();
        $currentEnvironmentHostname = $currentEnvironment->getHostname();

        $registrationDatabase = $api->getRegistrationDatabase(/* $writable= */ false);
        if (!$registrationDatabase)
            return false;  // no registration database is available

        foreach ($registrationDatabase->getRegistrations() as $registration) {
            if ($registration->getAuthToken() !== $authToken)
                continue;  // non-matching authentication token

            $role = $registration->getEventAcceptedRole($event);
            if (!$role)
                continue;  // non-participating authentication token

            $this->registration = $registration;

            // Administrators have all access, so skip the additional access checks.
            if ($registration->isAdministrator()) {
                $this->privileges = self::PRIVILEGE_ALL;
                break;
            }

            // Access to phone numbers is limited to Staff and Senior members of our volunteer
            // force, whereas cross-environment access is limited to an allowlist.
            $isStaff = stripos($role, 'Staff') !== false;
            $isSenior = stripos($role, 'Senior') !== false;

            $isCrossEnvironmentAllowedHost = in_array(
                    $currentEnvironmentHostname, self::CROSS_ENVIRONMENT_HOSTS_ALLOWLIST);

            if ($isStaff || $isSenior) {
                $this->privileges |= self::PRIVILEGE_PHONE_NUMBERS;
                $this->privileges |= self::PRIVILEGE_USER_NOTES;
            }

            if (($isStaff || $isSenior) && $isCrossEnvironmentAllowedHost)
                $this->privileges |= self::PRIVILEGE_CROSS_ENVIRONMENT;

            break;
        }

        if (!$this->registration)
            return false;  // no registration could be identified

        $this->environments = [[ $currentEnvironment, $registrationDatabase ]];

        if ($this->privileges & self::PRIVILEGE_CROSS_ENVIRONMENT) {
            foreach (EnvironmentFactory::getAll($api->getConfiguration()) as $environment) {
                if ($environment->getHostname() === $currentEnvironmentHostname)
                    continue;

                foreach ($environment->getEvents() as $environmentEvent) {
                    if ($environmentEvent->getIdentifier() !== $event)
                        continue;  // unrelated event

                    $environmentRegistrationDatabase = $api->getRegistrationDatabaseForEnvironment(
                            $environment, /* $writable= */ false);
                    if (!$environmentRegistrationDatabase)
                        continue;  // no registration database is available

                    $this->environments[] = [ $environment, $environmentRegistrationDatabase ];
                    break;
                }
            }
        }

        foreach ($currentEnvironment->getEvents() as $environmentEvent) {
            if ($environmentEvent->getIdentifier() !== $event)
                continue;  // unrelated event

            $this->environmentEvent = $environmentEvent;
            break;
        }

        return true;
    }

    // Populates the common events for the given |$event| based on the environment that the portal
    // is being used in. Other environments may provide their own JSON files, but this would be
    // rather uncommon, as our private events can be supplemented within the portal.
    private function populateEvents(string $event): array {
        $filename = $this->environmentEvent?->getProgram();
        if (!$filename)
            return [];  // no program is available for the given |$event|

        $program = json_decode(file_get_contents(Cache::CACHE_PATH . '/' . $filename), true);
        $events = [];

        foreach ($program as $entry) {
            $identifier = strval($entry['id']);
            $sessions = [];

            foreach ($entry['sessions'] as $session) {
                $sessions[] = [
                    'location'  => $this->createLocationId($session['location'], $session['floor']),
                    'name'      => $session['name'],
                    'time'      => [ $session['begin'], $session['end'] ],
                ];
            }

            $events[$identifier] = [
                'hidden'        => !!$entry['hidden'],
                'identifier'    => $identifier,
                'sessions'      => $sessions,
            ];

            if (array_key_exists('event', $this->notes)) {
                if (array_key_exists($identifier, $this->notes['event']))
                    $events[$identifier]['notes'] = $this->notes['event'][$identifier];
            }
        }

        return $events;
    }

    // Populates the volunteering information for the selected list of environments and associated
    // registration databases. Only the given |$event| will be considered for this selection.
    private function populateVolunteers(string $event): array {
        $volunteers = [];

        foreach ($this->environments as [ $environment, $registrationDatabase ]) {
            $environmentId = $environment->getShortName();

            foreach ($registrationDatabase->getRegistrations() as $registration) {
                $role = $registration->getEventAcceptedRole($event);
                if (!$role)
                    continue;  // non-participating event identifier

                $token = $registration->getUserToken();

                if (array_key_exists($token, $volunteers)) {
                    $volunteers[$token]['environments'][$environmentId] = $role;
                    continue;
                }

                $volunteer = [
                    'name'          => [
                        $registration->getFirstName(),
                        $registration->getLastName(),
                    ],
                    'identifier'    => $token,
                    'environments'  => [
                        $environmentId => $role
                    ],
                ];

                // Supplement privileged information to the volunteer's entry when allowed.
                if ($this->privileges & self::PRIVILEGE_ACCESS_CODES)
                    $volunteer['accessCode'] = $registration->getAccessCode();

                if ($this->privileges & self::PRIVILEGE_PHONE_NUMBERS)
                    $volunteer['phoneNumber'] = $registration->getPhoneNumber();

                if ($this->privileges & self::PRIVILEGE_USER_NOTES) {
                    if (array_key_exists('volunteer', $this->notes)) {
                        if (array_key_exists($token, $this->notes['volunteer']))
                            $volunteer['notes'] = $this->notes['volunteer'][$token];
                    }
                }

                // Supplement information about the user's avatar when it can be found on the file-
                // system. At some point we should optimize this check somehow.
                $avatarUrl = $registration->getAvatarUrl($environment);
                if ($avatarUrl !== null)
                    $volunteer['avatar'] = $avatarUrl;

                // Store the compiled |$volunteer| structure for the current volunteer.
                $volunteers[$token] = $volunteer;
            }
        }

        return $volunteers;
    }

    // Populates the shifts specific to this event. Shift mapping has the ability to create new
    // locations, as not all shifts can be associated with events on the programme, which is why
    // the associated properties are passed to this function by reference.
    private function populateShifts(
            string $event, Cache $cache, array &$areas, array &$events, array &$locations): array {

        // Event ID to use for event mapping entries that are misconfigured, for example by being
        // associated with an invalid event ID, or to be created in an area or location that does
        // not exist in the event configuration.
        $errorEventId = null;

        // Helper function to lazily get the error event to use for mapping having issues.
        $getOrCreateErrorEventId = function() use (&$areas, &$events, &$locations, &$errorEventId) {
            if ($errorEventId)
                return $errorEventId;

            // (1) Crete an area, location and event for aggregating faulty configuration.
            $areas['schedule-error-area'] = [
                'name'          => 'Schedule Errors',
                'identifier'    => 'schedule-error-area',
            ];

            $locations[] = [
                'identifier'    => 'schedule-error-location',
                'name'          => 'Schedule Errors',
                'area'          => 'schedule-error-area',
            ];

            $events['schedule-error'] = [
                'hidden'        => true,
                'identifier'    => 'schedule-error',
                'sessions'      => [
                    [
                        'location'      => 'schedule-error-location',
                        'name'          => 'Schedule Errors',
                        'time'          => [ time() - 1, time() + 1 ],
                    ],
                ],
            ];

            return $errorEventId = 'schedule-error';
        };

        $shifts = [];
        foreach ($this->environments as [ $environment, $registrationDatabase ]) {
            $scheduleDatabase = null;

            foreach ($environment->getEvents() as $environmentEvent) {
                if ($environmentEvent->getIdentifier() !== $event)
                    continue;  // unrelated event

                $settings = $environmentEvent->getScheduleDatabaseSettings();
                if (!is_array($settings))
                    continue;  // no data has been specified for this environment

                $spreadsheetId = $settings['spreadsheet'];

                $mappingSheet = $settings['mappingSheet'];
                $scheduleSheet = $settings['scheduleSheet'];
                $scheduleSheetStartDate = $settings['scheduleSheetStartDate'];

                $scheduleDatabase = ScheduleDatabaseFactory::openReadOnly(
                        $cache, $spreadsheetId, $mappingSheet, $scheduleSheet, $scheduleSheetStartDate);
                break;
            }

            if (!$scheduleDatabase)
                continue;  // no schedule database could be located for this environment

            $eventsPendingSessions = [];
            $identifierMapping = [];

            // (1) Process the |$scheduleDatabase|'s event mapping, and create events and locations
            //     as appropriate. Each of the identifiers will be added to |$identifierMapping|.
            foreach ($scheduleDatabase->getEventMapping() as $identifier => $mapping) {
                // (a) Event ID is given, attach the |$mapping| to that event.
                if (strlen($mapping['eventId'])) {
                    if (array_key_exists($mapping['eventId'], $events))
                        $identifierMapping[$identifier] = $mapping['eventId'];
                    else
                        $identifierMapping[$identifier] = $getOrCreateErrorEventId();

                    continue;
                }

                $locationId = $mapping['locationId'];

                // (b) Location ID is omitted, create a new location for the mapping. Both the area
                //     and location name must be provided, and the area must exist.
                if (!strlen($locationId)) {
                    if (!strlen($mapping['areaId']) || !strlen($mapping['locationName'])) {
                        $identifierMapping[$identifier] = $getOrCreateErrorEventId();
                        continue;
                    }

                    if (!array_key_exists($mapping['areaId'], $areas)) {
                        $identifierMapping[$identifier] = $getOrCreateErrorEventId();
                        continue;
                    }

                    $locationId = substr(md5($mapping['areaId'] . $mapping['locationName']), 0, 8);
                    if (!array_key_exists($locationId, $locations)) {
                        $locations[$locationId] = [
                            'identifier'    => $locationId,
                            'name'          => $mapping['locationName'],
                            'area'          => $mapping['areaId'],
                        ];
                    }
                }

                // (c) Event ID is omitted, create a new event for this mapping.
                $eventId = substr(md5($mapping['description'] . $locationId), 0, 8);
                if (!array_key_exists($eventId, $events)) {
                    $eventsPendingSessions[$identifier] = [
                        'location'  => $locationId,
                        'shifts'    => [],
                    ];

                    $events[$eventId] = [
                        'hidden'        => true,
                        'identifier'    => $eventId,
                        'sessions'      => [
                            [
                                // TODO: Delete this when session generation has been implemented.
                                'location'      => $locationId,
                                'name'          => 'Temporary session',
                                'time'          => [ time() - 1, time() + 1 ],
                            ],
                        ],
                    ];
                }

                $identifierMapping[$identifier] = $eventId;
            }

            // (2) Iterate over all the shifts, and include the ones for whom volunteers are known
            //     in the |$shifts|. Store sessions in the |$identifierPendingSessions| as well.
            // TODO

            // (3) For each of the entries in |$identifierPendingSessions|, calculate the actual
            //     sessions and add them to the event to make it clear when people are scheduled.
            // TODO
        }

        return [];
    }

    // Populates a list of the areas present in the event. The areas are made available in the
    // program's events, however, their names are contained in the portal's configuration.
    private function populateAreas(string $event): array {
        $mapping = $this->environmentEvent?->getAreas() ?? [];
        $areas = [];

        foreach ($this->locationCache as [ 'area' => $area ]) {
            if (array_key_exists($area, $areas))
                continue;

            if (array_key_exists($area, $mapping)) {
                $areas[$area] = array_merge($mapping[$area], [
                    'identifier'    => $area,
                ]);
            } else {
                $areas[$area] = [
                    'identifier'    => $area,
                    'name'          => 'Area ' . $area,
                ];
            }
        }

        return $areas;
    }

    // Populates the list of locations for the given event. This combines both the locations that
    // are part of the main programme, as well as the ones introduced by the schedules.
    private function populateLocations(): array {
        $locations = [];

        foreach ($this->locationCache as $name => [ 'area' => $area, 'hash' => $hash ]) {
            $locations[$hash] = [
                'identifier'    => $hash,

                'name'          => $name,
                'area'          => $area,
            ];
        }

        return $locations;
    }

    // Creates a hash of the given location |$name|. Cached results, so it's safe to call this
    // method multiple times without worrying too much about performance.
    private function createLocationId(string $name, mixed $area): string {
        if (array_key_exists($name, $this->locationCache))
            return $this->locationCache[$name]['hash'];

        $hash = substr(base_convert(hash('fnv164', $name . self::LOCATION_SALT), 16, 32), 0, 8);
        $this->locationCache[$name] = [
            'area'      => strval($area),
            'hash'      => $hash,
        ];

        return $hash;
    }
}
