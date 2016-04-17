// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const ConventionEvent = require('./convention_event');
const ConventionEventSession = require('./convention_event_session');
const ConventionLocation = require('./convention_location');
const ConventionVolunteer = require('./convention_volunteer');

// The loader responsible for making sure that the convention information can be retrieved. There
// are two sources to load the data from: the network, or the local cache.
class ConventionLoader {
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
                    return resolve(this.loadSchedule(JSON.parse(request.responseText)));
                } catch (e) {
                    return reject(new Error('Server error: ' + e.message));
                }
            });

            // Treat network errors as success without result, we won't cycle the data.
            request.addEventListener('error', () => resolve(null));

            request.open('POST', endpoint, true);
            request.send(name);
        });
    }

    // Attempts to interpret |data| as the convention's schedule. Returns a Promise that will be
    // resolved when all data is valid and available in the current Convention object.
    loadSchedule(uncheckedData) {
        const data = {
            events: uncheckedData.events || [],
            volunteers: uncheckedData.volunteers || []
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

            let events = [];
            data.events.forEach(eventData => {
                const event = new ConventionEvent(eventData.hidden);

                eventData.sessions.forEach(sessionData => {
                    event.sessions.push(
                          new ConventionEventSession(sessionData, event,
                                                     locations[sessionData.location]));
                });

                events.push(event);
            });

            let volunteers = [];
            data.volunteers.forEach(volunteer =>
                volunteers.push(new ConventionVolunteer(volunteer)));

            // Returns the loaded information as an object so that the caller can store it.
            return {
                events: events,
                locations: Object.values(locations),
                volunteers: volunteers
            };
        });
    }
}

module.exports = ConventionLoader;
