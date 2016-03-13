<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// The import-program service is responsible for downloading the events and rooms in which events
// will be taking place. The format of the input is entirely proprietary to AnimeCon, so an
// intermediate format has been developed to make adoption for other input types easier.
//
// For the Anime 2016 conference, the JSON format has been chosen to serve as the input for this
// website's data. It expects an array of event entries that each have the following fields:
//
//     'name'        Name of the event. May be suffixed with 'opening' or 'closing'.
//     'start'       Start time of the event in full ISO 8601 format.
//     'duration'    Duration, in minutes, of the event. May be zero.
//     'end'         End time of the event in full ISO 8601 format.
//     'type'        Type of event. See below for a list of currently used values.
//     'location'    Name of the location where the event will be taking place.
//     'image'       Image describing the event. Relative to some base URL I haven't found yet.
//     'comment'     Description of the event. May be NULL.
//     'hidden'      Whether the event should be publicly visible.
//     'locationId'  Internal id of the location in the AnimeCon database.
//     'floor'       Floor on which the event takes place. Prefixed with 'floor-'. {-1, 0, 1, 2}.
//     'floorTitle'  Description of the floor on which the event takes place.
//     'tsId'        I have absolutely no idea.
//     'eventId'     Internal id of this event in the AnimeCon database.
//     'opening'     `0` for a one-shot event, `1` for an event's opening, `-1` for its closing.
//
// The current list of values used in the 'type' enumeration:
//
//     compo, concert, cosplaycompo, cosplayevent, event, event18, Internal, lecture, open,
//     themevideolive, workshop
//
// A number of things have to be considered when considering this input format:
//
//     (1) The opening and closing of larger events has been split up in two separate entries.
//     (2) Location names are not to be relied upon and may be changedd at moment's notice. Using
//         the locationId as a unique identifier will be more stable.
//
// Because the input data may change from underneath us at any moment, a validation routing has been
// included in this service that the input must pass before it will be considered for importing.
// Failures will raise an exception, because they will need manual consideration.
class ImportProgramService implements Service {

}
