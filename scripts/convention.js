// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const ConventionLoader = require('./convention_loader');
const DateUtils = require('./date_utils');

// Represents the convention whose volunteers this portal has been built for. It has knowledge of
// the volunteers, locations and events, to the extend the server thinks it's appropriate for them
// to know about- controlled using the local user's token.
class Convention {
    constructor(user) {
        this.loader_ = new ConventionLoader();

        this.events_ = [];
        this.locations_ = [];
        this.volunteers_ = [];

        // Observe the |user| class to be informed of user state changes.
        user.observe(this.__proto__.onUserStateChanged.bind(this));
    }

    // Gets the list of events that will take place as part of this convention.
    get events() { return this.events_; }

    // Gets the list of locations that will be hosting events as part of this convention.
    get locations() { return this.locations_; }

    // Gets the volunteers for this convention. The list will be sorted by their full name.
    get volunteers() { return this.volunteers_; }

    // Finds a volunteer named |name| and returns the ConventionVolunteer instance when found, or
    // NULL when not found. When |isSlug| is set to true, the |name| will be compared to the
    // volunteer's slug rather than their name.
    findVolunteer(name, isSlug = false) {
        return this.volunteers_.find(volunteer => {
            if (!isSlug && name === volunteer.name)
                return volunteer;

            if (isSlug && name === volunteer.slug)
                return volunteer;

        }) || null;
    }

    // Finds the active and upcoming events for each known location. The |activeOnly| and |floor|
    // constraints may optionally be given to limit the set of returned events.
    findUpcomingEvents({ activeOnly = false, floor = null, hidden = false } = {}) {
        const currentTime = DateUtils.getTime();
        const maxUpcomingPerLocation = 2;

        let floors = {};

        this.locations_.forEach(location => {
            if (floor !== null && location.floor !== floor)
                return;

            let sessions = [];

            location.sessions.forEach(session => {
                if (session.isHidden() && !hidden)
                    return;

                if (session.endTime < currentTime)
                    return;

                const isActive = session.beginTime <= currentTime;
                if (!isActive && activeOnly)
                    return;

                if (!isActive && sessions.length >= maxUpcomingPerLocation)
                    return;

                sessions.push(session);
            });

            if (!floors.hasOwnProperty(location.floor))
                floors[location.floor] = [];

            floors[location.floor].push({
                location: location,
                sessions: sessions
            });
        });

        return floors;
    }

    // Loads all information of the convention available to |user|. Returns a Promise that will be
    // resolved with this Convention instance when the information is available, or will be rejected
    // with an error in case the information cannot be loaded.
    loadForUser(user) {
        if (!user.isIdentified())
            return Promise.reject(new Error('The user must be identified for loading the data.'));

        // TODO: Load a locally cached version of the convention data prior to hitting the network,
        //       since the initial draw for logged in volunteers will be blocking on this.

        return this.loader_.fetchScheduleFromNetwork(user.token).then(data => {
            this.events_ = data.events;
            this.locations_ = data.locations;
            this.volunteers_ = data.volunteers;
            return this;
        });
    }

    // Will be invoked when the user identifies to an account, or signs out of their account. The
    // convention's information will either have to be loaded, or discarded.
    onUserStateChanged(user) {
        if (user.isIdentified()) {
            this.loadForUser(user);
            return;
        }

        this.events_ = [];
        this.locations_ = [];
        this.volunteers_ = [];
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////
    // TODO: These methods exist whilst I transition the existing schedule implementation.

    GetCurrentEvents() { return { '-1': [], 0: [], 1: [], 2: [] }; }
}

module.exports = Convention;
