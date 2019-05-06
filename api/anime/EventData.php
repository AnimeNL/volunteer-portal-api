<?php
// Copyright 2019 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// Main class responsible for composing the collection of event data accessible to a particular
// user. The data returned by this class must conform to the documented API interface:
//
// https://github.com/AnimeNL/portal/blob/master/API.md#apievent
class EventData {
    // File in which the program data will be written.
    const EVENT_PROGRAM = __DIR__ . '/../configuration/program.json';

    // The locations that exist for this event. The program import reads them as strings, but they
    // need to be shared with unique Ids according to the API.
    private $locations;

    // The program for this event.
    private $program;

    // The volunteer for whom the data is being compiled.
    private $volunteer;

    // The group token of the environment that the volunteer is part of.
    private $volunteerGroupToken;

    // The environments to which this volunteer has access.
    private $environments;

    public function __construct(Environment $environment, Volunteer $volunteer) {
        $this->volunteer = $volunteer;
        $this->volunteerGroupToken = $environment->getGroupToken();

        if ($this->volunteer->isAdmin())
            $this->environments = Environment::getAll();
        else
            $this->environments = [ $environment ];

        $this->locations = [];
        $this->program = $this->loadProgram();
    }

    // Loads the program for the event. The global program will be considered, as well as programs
    // unique to each of the environments this volunteer has access to. This method will also
    // initialize the |$locations| mapping.
    private function loadProgram() : array {
        $currentLocationId = 1;

        $program = json_decode(file_get_contents(self::EVENT_PROGRAM), true);
        // TODO: Support team-level programs.

        // Sort the |$program| to make sure they're in incrementing order.
        usort($program, function($lhs, $rhs) {
            return $lhs['sessions'][0]['begin'] > $rhs['sessions'][0]['begin'];
        });

        // Identify the unique locations that are included in the |$program|.
        foreach ($program as $programEvent) {
            foreach ($programEvent['sessions'] as $programSession) {
                if (array_key_exists($programSession['location'], $this->locations))
                    continue;

                $this->locations[$programSession['location']] = [
                    $currentLocationId++,
                    $programSession['floor'],
                ];
            }
        }

        return $program;
    }

    // Returns an array detailing the events that will take place.
    public function getEvents() : array {
        $events = [];

        foreach ($this->program as $event) {
            $sessions = [];

            // TODO: Filter for hidden events?

            foreach ($event['sessions'] as $session) {
                $sessions[] = [
                    'name'          => $session['name'],
                    'description'   => $session['description'],
                    'locationId'    => $this->locations[$session['location']][0],
                    'beginTime'     => $session['begin'],
                    'endTime'       => $session['end'],
                ];
            }

            $events[] = [
                'id'        => $event['id'],
                'internal'  => $event['hidden'],
                'sessions'  => $sessions,
            ];
        }

        return $events;
    }

    // Returns an array detailing the floors of the event's venue.
    public function getFloors() : array {
        return [
            [
                'id'      => 0,
                'label'   => 'Halls',
                'icon'    => '/static/images/floors.svg#halls',
            ],
            [
                'id'      => 1,
                'label'   => 'Ports',
                'icon'    => '/static/images/floors.svg#ports',
            ],
            [
                'id'      => 2,
                'label'   => 'Conference',
                'icon'    => '/static/images/floors.svg#conference',
            ],
            [
                'id'      => 3,
                'label'   => 'Docks',
                'icon'    => '/static/images/floors.svg#docks',
            ]
        ];
    }

    // Returns an array detailing the locations available for this event.
    public function getLocations() : array {
        $locations = [];

        foreach ($this->locations as $label => [$id, $floorId]) {
            $locations[] = [
                'id'       => $id,
                'floorId'  => $floorId,
                'label'    => $label
            ];
        }

        return $locations;
    }

    // Returns an array detailing the available volunteer groups.
    public function getVolunteerGroups() : array {
        $groups = [];

        foreach ($this->environments as $environment) {
            $isPrimary = $this->volunteerGroupToken === $environment->getGroupToken();

            $groups[] = [
                'groupToken'   => $environment->getGroupToken(),
                'primary'      => $isPrimary,
                'label'        => $environment->getGroupName()
            ];
        }

        return $groups;
    }

    // Returns an array detailing the available volunteers.
    public function getVolunteers() : array {
        $isAdmin = $this->volunteer->isAdmin();
        $volunteers = [];

        foreach ($this->environments as $environment) {
            $groupToken = $environment->getGroupToken();
            foreach ($environment->loadVolunteers() as $volunteer) {
                if ($volunteer->isHidden())
                    continue;

                $accessCode = $isAdmin ? $volunteer->getAccessCode() : null;
                $telephone = $isAdmin || $volunteer->isSeniorVolunteer()
                    ? $volunteer->getTelephone()
                    : null;

                $volunteers[] = [
                    'userToken'    => $volunteer->getUserToken(),
                    'groupToken'   => $groupToken,
                    'name'         => $volunteer->getName(),
                    'avatar'       => null,
                    'title'        => $environment->typeToTitle($volunteer->getType()),
                    'accessCode'   => $accessCode,
                    'telephone'    => $telephone,
                ];
            }
        }

        return $volunteers;
    }
}
