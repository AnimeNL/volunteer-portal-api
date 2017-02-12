// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

var DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

(function() {
    var container = document.getElementById('schedule');

    // UNIX timestamps representing the begin and end of the conventions's scheduled events.
    var conventionDuration = { begin: Number.MAX_VALUE, end: Number.MIN_VALUE };

    // Mapping of floors to an array of rooms, each being an array of events taking place there.
    var conventionEvents = {};

    // Iterate over all events to determine the duration of the convention.
    Object.keys(schedule).forEach(eventId => {
        schedule[eventId].sessions.forEach(session => {
            conventionDuration.begin = Math.min(conventionDuration.begin, session.begin);
            conventionDuration.end = Math.max(conventionDuration.end, session.end);
        });
    });

    // Respectively floor and ceil the convention's duration to full hours.
    conventionDuration.begin -= conventionDuration.begin % 3600;
    conventionDuration.end += conventionDuration.end % 3600;

    // Determine number of seconds of difference between the current timezone and that of the event.
    var timezoneCorrection =
        ((new Date()).getTimezoneOffset() -
            moment.tz.zone('Europe/Amsterdam').offset(conventionDuration.begin)) * 60;

    conventionDuration.begin += timezoneCorrection;
    conventionDuration.end += timezoneCorrection;

    // Iterate over all events once more to normalize the data based on floor and location.
    Object.keys(schedule).forEach(eventId => {
        var event = schedule[eventId];

        event.sessions.forEach(session => {
            session.begin += timezoneCorrection;
            session.end += timezoneCorrection;

            if (!conventionEvents.hasOwnProperty(session.floor))
                conventionEvents[session.floor] = {};

            if (!conventionEvents[session.floor].hasOwnProperty(session.location))
                conventionEvents[session.floor][session.location] = [];

            conventionEvents[session.floor][session.location].push({
                name: session.name,
                description: session.description,
                hidden: event.hidden,
                begin: session.begin,
                end: session.end
            });
        });
    });

    // Translate the convention's hours to an array of objects containing { day, hours }.
    var conventionHourCount = 0;
    var conventionHourStart = new Date(conventionDuration.begin * 1000).getHours();
    var conventionDays = [];

    for (var hour = conventionDuration.begin; hour < conventionDuration.end;) {
        var date = new Date(hour * 1000);

        var displayDay = date.getDay();
        var displayHour = date.getHours();
        
        var hours = [];
        for (; displayHour < 24 && hour < conventionDuration.end; displayHour++) {
            hours.push(displayHour);

            conventionHourCount++;
            hour += 3600;
        }

        conventionDays.push({
            day: displayDay,
            hours: hours
        });
    }

    var table = document.createElement('table');
    table.setAttribute('cellspacing', 0);

    var tableHeader = document.createElement('thead');
    var tableBody = document.createElement('tbody');

    // Render the header that will list the days and hours for each day.
    var headerDays = createPaddedHeaderRow();
    var headerHours = createPaddedHeaderRow();

    conventionDays.forEach(conventionDay => {
        headerDays.appendChild(createDayCell(conventionDay.day, conventionDay.hours.length));

        conventionDay.hours.forEach(hour =>
            headerHours.appendChild(createHourCell(hour)));
    });

    tableHeader.appendChild(headerDays);
    tableHeader.appendChild(headerHours);

    // Render the table that will contain the event information.
    Object.keys(conventionEvents).sort((lhs, rhs) => lhs > rhs).forEach(floor => {
        var locations = Object.keys(conventionEvents[floor]);

        // Iterate over the locations on this floor. The first location will also create a column
        // that indicates the floor number the current set of locations can be found on.
        for (var locationId = 0; locationId < locations.length; ++locationId) {
            var events = conventionEvents[floor][locations[locationId]];
            var row = document.createElement('tr');

            var styleName = 'floor-' + floor + '-' + locationId;
            if (locationId == 0) {
                row.appendChild(createFloorCell(floor, locations.length, styleName));
                row.className = 'new-floor';
            }

            row.appendChild(createLocationCell(locations[locationId], styleName));

            var maximumSimultaneousEvents = determineMaximumSimultaneousEvents(events);
            var firstCell = createEmptyHourCell(conventionHourStart, styleName);

            events.forEach(event => {
                var eventBubble = createEventBubble(event);
                eventBubble.style.marginLeft = ((event.begin - conventionDuration.begin) / 3600) * 50 + 'px';
                eventBubble.style.marginTop = event.active * 24 + 'px';

                firstCell.appendChild(eventBubble);
            });

            row.style.height = maximumSimultaneousEvents * 25 + 'px';
            row.appendChild(firstCell);

            for (var hour = 1; hour < conventionHourCount; ++hour)
                row.appendChild(createEmptyHourCell((conventionHourStart + hour) % 24, styleName));

            tableBody.appendChild(row);
        }
    });

    table.appendChild(tableHeader);
    table.appendChild(tableBody);

    // Force the width of the table to match its actual size.
    table.setAttribute('width', 30 + 315 + conventionHourCount * 50);

    container.appendChild(table);

    // ---------------------------------------------------------------------------------------------

    // Determines the maximum number of simultaneous events in |events|, and writes the number of
    // active events to the entries in |events| so their vertical arrangement can be created.
    function determineMaximumSimultaneousEvents(events) {
        var eventTimes = [];

        events.forEach(event => {
            eventTimes.push({ type: 'begin', time: event.begin, event: event });
            eventTimes.push({ type: 'end', time: event.end });
        });

        var maximumActive = Number.MIN_VALUE;
        var currentActive = 0;

        eventTimes.sort((lhs, rhs) => {
            if (lhs.time === rhs.time) {
                if (lhs.type === 'begin' && rhs.type !== 'begin') return 1;
                if (lhs.type !== 'begin' && rhs.type === 'begin') return -1;
                return 0;
            }

            return lhs.time > rhs.time ? 1 : -1;
        });

        eventTimes.forEach(entry => {
            if (entry.type === 'begin') {
                entry.event.active = currentActive;  // annotate the event

                maximumActive = Math.max(maximumActive, ++currentActive);
            } else {
                --currentActive;
            }
        });

        if (eventTimes[0].type !== 'begin' || eventTimes[0].event.active !== 0)
            console.log(eventTimes);


        return maximumActive;
    }

    // Creates a cell for leading horizontal padding in the header.
    function createPaddedHeaderRow() {
        var paddingCell = document.createElement('th');
        paddingCell.setAttribute('colspan', 2);

        var row = document.createElement('tr');
        row.appendChild(paddingCell);

        return row;
    }

    // Creates a cell for displaying a day name in the header.
    function createDayCell(day, hourCount) {
        var cell = document.createElement('th');
        cell.setAttribute('colspan', hourCount);
        cell.className = 'header-day';
        cell.textContent = DAYS[day];

        return cell;
    }

    // Creates a cell for displaying an hour value in the header.
    function createHourCell(hour) {
        var cell = document.createElement('td');
        cell.className = 'header-hour';
        cell.textContent = hour;

        if (hour === 0)
            cell.className += ' new-day';

        return cell;
    }

    // Creates a cell for a floor, having the floor's name and spanning multiple rows.
    function createFloorCell(floor, locationCount, styleName) {
        var cell = document.createElement('td');
        cell.setAttribute('rowspan', locationCount);
        cell.className = styleName + ' floor-' + floor + ' floor';
        cell.textContent = floor;

        return cell;
    }

    // Creates a cell for an individual location, having the location's name.
    function createLocationCell(location, styleName) {
        var cell = document.createElement('td');
        cell.className = styleName + ' location';
        cell.textContent = location;

        return cell;
    }

    // Creates a cell for an `hour` view in the schedule table.
    function createEmptyHourCell(hour, styleName) {
        var cell = document.createElement('td');
        cell.className = styleName + ' hour';

        if (hour === 0)
            cell.className += ' new-day';

        return cell;
    }

    // Creates an information bubble for an event that will take place.
    function createEventBubble(event) {
        var bubble = document.createElement('div');
        bubble.className = 'event';

        if (event.hidden)
            bubble.className += ' hidden';

        bubble.textContent = event.name;

        var prefix = '';
        {
            var start = new Date(event.begin * 1000);
            var end = new Date(event.end * 1000);

            var padTime = value => ('0' + value).substr(-2);

            var startTime = padTime(start.getHours()) + ':' + padTime(start.getMinutes());
            var endTime = padTime(end.getHours()) + ':' + padTime(end.getMinutes());

            prefix = '[' + startTime + ' - ' + endTime + '] ';
        }

        bubble.title = prefix + event.name;
        bubble.style.width = (((event.end - event.begin) / 3600) * 50 - 5) + 'px';

        return bubble;
    }
})();
