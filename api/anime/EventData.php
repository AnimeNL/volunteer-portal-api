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

    // File in which the program's notes will be written.
    const EVENT_NOTES = __DIR__ . '/../configuration/notes.json';

    // The locations that exist for this event. The program import reads them as strings, but they
    // need to be shared with unique Ids according to the API.
    private $locations;

    // The program for this event.
    private $program;

    // The notes for this event.
    private $notes;

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
        $this->notes = json_decode(file_get_contents(self::EVENT_NOTES), true);
    }

    // Loads the program for the event. The global program will be considered, as well as programs
    // unique to each of the environments this volunteer has access to. This method will also
    // initialize the |$locations| mapping.
    private function loadProgram() : array {
        $currentLocationId = 1;

        $program = json_decode(file_get_contents(self::EVENT_PROGRAM), true);

        // Load the events unique to the environments this volunteer has access to.
        foreach ($this->environments as $environment) {
            foreach ($environment->loadProgram() as $programEntry)
                array_push($program, $programEntry);
        }

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
            $notes = null;

            if (array_key_exists($event['id'], $this->notes))
                $notes = $this->notes[$event['id']];

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
                'notes'     => $notes,
                'sessions'  => $sessions,
            ];
        }

        return $events;
    }

    // Returns an array detailing the floors of the event's venue.
    public function getFloors() : array {
        return [
            [
                'id'          => 0,
                'label'       => 'Halls',
                'iconColor'   => '#F44336',
                'icon'        => '/static/images/floors.svg#halls',
            ],
            [
                'id'          => 1,
                'label'       => 'Ports',
                'iconColor'   => '#00897B',
                'icon'        => '/static/images/floors.svg#ports',
            ],
            [
                'id'          => 2,
                'label'       => 'Conference',
                'iconColor'   => '#6D4C41',
                'icon'        => '/static/images/floors.svg#conference',
            ],
            [
                'id'          => 3,
                'label'       => 'Docks',
                'iconColor'   => '#FB8C00',
                'icon'        => '/static/images/floors.svg#docks',
            ]
        ];
    }

    // Returns an array containing |key=>value| pairs to display on the Internals page. Only
    // populated for administrators with debugging capabilities.
    public function getInternalNotes() : array {
        if (!$this->volunteer->isAdmin() || !$this->volunteer->isDebug())
            return [];

        $notes = [];

        // (1) List the access codes of all Hidden volunteers.
        foreach ($this->environments as $environment) {
            foreach ($environment->loadVolunteers() as $volunteer) {
                if (!$volunteer->isHidden())
                    continue;

                $key = 'Access code (' . $volunteer->getName() . ')';
                $value = $volunteer->getAccessCode();

                $notes[$key] = $value;
            }
        }

        return $notes;
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

    // Returns an array detailing the shifts that are to take place during the event.
    public function getShifts() : array {
        $shifts = [];
        $volunteers = [];

        foreach ($this->environments as $environment) {
            foreach ($environment->loadVolunteers() as $volunteer)
                $volunteers[$volunteer->getName()] = $volunteer->getUserToken();
        }

        foreach ($this->environments as $environment) {
            foreach ($environment->loadShifts() as $volunteerName => $environmentShifts) {
                if (!array_key_exists($volunteerName, $volunteers)) {
                    // TODO: We should log this somewhere, as it's a data loss.
                    continue;
                }

                $userToken = $volunteers[$volunteerName];

                foreach ($environmentShifts as $shift) {
                    $shifts[] = [
                        'userToken'    => $userToken,
                        'type'         => $shift['shiftType'],
                        'eventId'      => $shift['eventId'],
                        'beginTime'    => $shift['beginTime'],
                        'endTime'      => $shift['endTime'],
                    ];
                }
            }
        }

        return $shifts;
    }

    // Returns whether the access code of |$volunteer| can be disclosed.
    //
    // Volunteers can view access codes of users who are less senior than they are. Admins are an
    // exception, who can see all access codes. Only admins can see the access codes of other
    // admins.
    private function discloseAccessCode($volunteer) : bool {
        if ($this->volunteer->isAdmin())
            return true;

        if ($volunteer->isAdmin())
            return false;

        switch ($this->volunteer->getType()) {
            case Volunteer::TYPE_VOLUNTEER:
                return false;

            case Volunteer::TYPE_SENIOR:
                return $volunteer->getType() === Volunteer::TYPE_VOLUNTEER;

            case Volunteer::TYPE_HIDDEN:
            case Volunteer::TYPE_STAFF:
                return $volunteer->getType() === Volunteer::TYPE_VOLUNTEER ||
                       $volunteer->getType() === Volunteer::TYPE_SENIOR;
        }

        return false;
    }

    // Returns whether the telephone number of |$volunteer| can be disclosed.
    //
    // Volunteers can see the telephone numbers of both the senior and staff levels. People in
    // either the senior of staff levels, and admins, can see the phone numbers of everyone.
    private function discloseTelephone($volunteer) : bool {
        if ($volunteer->isSeniorVolunteer())
            return true;

        if ($this->volunteer->isSeniorVolunteer())
            return true;

        if ($this->volunteer->isAdmin())
            return true;

        return false;
    }

    // Returns an array detailing the available volunteers.
    public function getVolunteers() : array {
        $volunteers = [];

        foreach ($this->environments as $environment) {
            $groupToken = $environment->getGroupToken();
            foreach ($environment->loadVolunteers() as $volunteer) {
                if ($volunteer->isHidden())
                    continue;

                $accessCode = $this->discloseAccessCode($volunteer) ? $volunteer->getAccessCode()
                                                                    : null;

                $telephone = $this->discloseTelephone($volunteer) ? $volunteer->getTelephone()
                                                                  : null;

                $volunteers[] = [
                    'userToken'    => $volunteer->getUserToken(),
                    'groupToken'   => $groupToken,
                    'name'         => $volunteer->getName(),
                    'avatar'       => $volunteer->getPhoto(),
                    'title'        => $environment->typeToTitle($volunteer->getType()),
                    'accessCode'   => $accessCode,
                    'telephone'    => $telephone,
                ];
            }
        }

        return $volunteers;
    }
}
