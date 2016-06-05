// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const ConventionEvent = require('./convention_event');
const ConventionEventSession = require('./convention_event_session');
const ConventionLocation = require('./convention_location');
const ConventionVolunteer = require('./convention_volunteer');

// Creates a hash value for the |string|. Should not be used for cryptographic purposes.
function stringHash(string, hash = 0) {
    for (let i = 0; i < string.length; ++i)
        hash = ((hash << 5) - hash + string.charCodeAt(i)) << 0;

    return hash;
}

// The loader responsible for making sure that the convention information can be retrieved. There
// are two sources to load the data from: the network, or the local cache.
class ConventionLoader {
    constructor() {
        this.hash_ = null;
    }

    // Loads all information of the convention. The token to use for authenticaiton must already be
    // known to the instance. Returns a Promise that will be resolved with the Convention object
    // in case of success, or rejected in case of an exceptional error.
    fetchScheduleFromNetwork(token) {
        if (token === null)
            return Promise.reject(new Error('Unable to fetch data without an auth token.'));

        const endpoint = '/anime/convention.php?token=' + token;

        return new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();
            request.addEventListener('load', () => {
                try {
                    return resolve(request.responseText);
                } catch (e) {
                    return reject(new Error('Server error: ' + e.message));
                }
            });

            // Treat network errors as success without result, we won't cycle the data.
            request.addEventListener('error', () => resolve(null));

            request.open('GET', endpoint, true);
            request.send();
        });
    }

    // Loads the schedule based on the information fetched from the network.
    loadScheduleFromNetwork(token) {
        return this.fetchScheduleFromNetwork(token).then(response => {
            this.hash_ = stringHash(response);

            // Proceed with loading the schedule, and return when that has been done.
            return this.loadSchedule(JSON.parse(response));
        });
    }

    // Fetches the latest schedule from the network and returns whether it has changed based on
    // the version that has been loaded for this request.
    isUpdateAvailable(token) {
        return this.fetchScheduleFromNetwork(token).then(response => {
            return this.hash_ !== stringHash(response);
        });
    }

    // Attempts to interpret |data| as the convention's schedule. Returns a Promise that will be
    // resolved when all data is valid and available in the current Convention object.
    loadSchedule(uncheckedData) {
        const data = {
            events: uncheckedData.events || [],
            volunteers: uncheckedData.volunteers || [],
            shifts: uncheckedData.shifts || []
        };

        return Promise.resolve().then(() => {
            let locations = {};
            data.events.forEach(eventData => {
                eventData.sessions.forEach(sessionData => {
                    if (locations.hasOwnProperty(sessionData.location))
                        return;

                    locations[sessionData.location] =
                        new ConventionLocation(sessionData.location, sessionData.floor);
                });
            });

            let events = {};
            data.events.forEach(eventData => {
                const event = new ConventionEvent(eventData.id, eventData.hidden);

                eventData.sessions.forEach(sessionData => {
                    event.sessions.push(
                          new ConventionEventSession(sessionData, event,
                                                     locations[sessionData.location]));
                });

                events[event.id] = event;
            });

            let volunteers = {};
            data.volunteers.forEach(volunteerName => {
                const volunteer = new ConventionVolunteer(volunteerName);
                volunteers[volunteer.name] = volunteer;
            });

            Object.keys(data.shifts).forEach(volunteerName => {
                if (!volunteers.hasOwnProperty(volunteerName)) {
                    console.warn('Dropping schedule for unknown volunteer: ', volunteerName);
                    return;
                }

                const volunteer = volunteers[volunteerName];

                data.shifts[volunteerName].forEach(shift => {
                    // Convert the UNIX timestamps to JavaScript times.
                    shift.beginTime *= 1000;
                    shift.endTime *= 1000;

                    switch (shift.shiftType) {
                        case 'available':
                            // don't do anything special with these shifts
                            break;
                        case 'unavailable':
                            volunteer.addUnavailableTime(shift.beginTime, shift.endTime);
                            break;
                        case 'event':
                            if (!events.hasOwnProperty(shift.eventId)) {
                                console.warn('Dropping shift for volunteer ' + volunteerName +
                                             ' due to unknown shift: ', shift);
                                return;
                            }

                            const event = events[shift.eventId];

                            volunteer.addShift(event, shift.beginTime, shift.endTime);
                            event.addShift(volunteer, shift.beginTime, shift.endTime);
                            break;
                    }
                });
            });

            // Sort the sessions in locations now that all information is available.
            Object.keys(locations).forEach(locationName => locations[locationName].sortSessions());

            // Returns the loaded information as an object so that the caller can store it.
            return {
                events: Object.values(events),
                locations: Object.values(locations),
                volunteers: Object.values(volunteers)
            };
        });
    }
}

module.exports = ConventionLoader;
