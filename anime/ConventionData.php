<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// Object responsible for compiling the data that should be exposed to a given volunteer.
class ConventionData {
    // Compiles the convention data required by the front-end for the |$volunteer|. The used data
    // will depend on the active |$environment|. Returns an array that could be send to the user.
    public static function CompileForVolunteer(Environment $environment, Volunteer $volunteer) {
        $compiler = new ConventionData($environment, $volunteer);
        return [
            'volunteers'    => $compiler->compileVolunteers()
        ];
    }

    // The environment based on which data is being compiled.
    private $environment;

    // The volunteer for whom data is being compiled.
    private $volunteer;

    private function __construct(Environment $environment, Volunteer $volunteer) {
        $this->environment = $environment;
        $this->volunteer = $volunteer;
    }

    // Compiles an array with all volunteers that should be sent to the user. Hidden volunteers will
    // be ignored for all levels. Staff and Seniors will receive telephone numbers and hotel
    // information of all the volunteers as well as the generic data.
    private function compileVolunteers() : array {
        $volunteers = [];

        foreach ($this->environment->loadVolunteers() as $volunteer) {
            if ($volunteer->getVisible() === false)
                continue;

            $volunteerData = [
                'name'      => $volunteer->getName(),
                'type'      => $volunteer->getType()
            ];

            // Append the extra information if the volunteer should have access to it.
            if ($this->isSeniorVolunteer()) {
                $volunteerData['telephone'] = $volunteer->getTelephone();
                $volunteerData['hotel'] = $volunteer->getHotel();
            }

            $volunteers[] = $volunteerData;
        }

        return $volunteers;
    }

    // Returns whether the current volunteer is of either the Senior or Staff level.
    private function isSeniorVolunteer() : bool {
        return $this->volunteer->getType() === Volunteer::TYPE_SENIOR ||
               $this->volunteer->getType() === Volunteer::TYPE_STAFF;
    }
}
