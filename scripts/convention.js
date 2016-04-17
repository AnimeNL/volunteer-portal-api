// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const ConventionEvent = require('./convention_event');
const ConventionLocation = require('./convention_location');
const Volunteer = require('./volunteer');

// Represents the convention whose volunteers this portal has been built for. It has knowledge of
// the volunteers, locations and events, to the extend the server thinks it's appropriate for them
// to know about- controlled using the local user's token.
class Convention {
    constructor(user) {
        this.loaded_ = false;
        this.token_ = null;

        this.events_ = [];
        this.locations_ = [];
        this.volunteers_ = [];

        // Observe the |user| class to be informed of user state changes.
        user.observe(this.__proto__.onUserStateChanged.bind(this));
    }

    // Returns whether information about the convention has been loaded.
    isLoaded() { return this.loaded_; }

    // Gets the list of events that will take place as part of this convention.
    get events() { return this.events_; }

    // Gets the list of locations that will be hosting events as part of this convention.
    get locations() { return this.locations_; }

    // Gets the volunteers for this convention. The list will be sorted by their full name.
    get volunteers() { return this.volunteers_; }

    // Loads all information of the convention available to |user|. Returns a Promise that will be
    // resolved with this Convention instance when the information is available, or will be rejected
    // with an error in case the information cannot be loaded.
    loadForUser(user) {
        if (!user.isIdentified())
            return Promise.reject(new Error('The user must be identified for loading the data.'));

        this.token_ = user.token;

        // TODO: Load a locally cached version of the convention data prior to hitting the network,
        //       since the initial draw for logged in volunteers will be blocking on this.

        return this.fetchScheduleFromNetwork();
    }

    // Loads all information of the convention. The token to use for authenticaiton must already be
    // known to the instance. Returns a Promise that will be resolved with the Convention object
    // in case of success, or rejected in case of an exceptional error.
    fetchScheduleFromNetwork() {
        if (this.token_ === null)
            return Promise.reject(new Error('Unable to fetch data without an auth token.'));

        const endpoint = '/anime/convention.php?token=' + this.token_;

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
            data.events.forEach(event => {
                if (locations.hasOwnProperty(event.location))
                    return;

                locations[event.location] = new ConventionLocation(event.location, event.floor);
            });

            let events = [];
            data.events.forEach(event =>
                events.push(new ConventionEvent(event, locations[event.location])));

            let volunteers = [];
            data.volunteers.forEach(volunteer =>
                volunteers.push(new Volunteer(volunteer)));

            // Now that all data has been imported, mark the Convention's data as having loaded and
            // copy over all data to their member variables.
            this.loaded_ = true;

            this.events_ = events;
            this.locations_ = Object.values(locations);
            this.volunteers_ = volunteers;
        });
    }

    // Will be invoked when the user identifies to an account, or signs out of their account. The
    // convention's information will either have to be loaded, or discarded.
    onUserStateChanged(user) {
        if (user.isIdentified()) {
            this.loadForUser(user);
            return;
        }

        this.token_ = null;

        // TODO: Clean up any additional other state here.
        // TODO: Invoke any observers of the Convention class about data changes.
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////
    // TODO: These methods exist whilst I transition the existing schedule implementation.

    GetCurrentEvents() { return { '-1': [], 0: [], 1: [], 2: [] }; }

    GetSteward(name) {
        for (let volunteer of this.volunteers_) {
            if (volunteer.name === name)
                return volunteer;
        }

        return null;
    }

    GetStewards() { return this.volunteers_; }
}

module.exports = Convention;
