// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const Utils = require('./utils');

// Represents one of the locations in which events will take place during the convention. It has an
// associated name and floor, and provides convenience methods for the events taking place in it.
class ConventionLocation {
    constructor(name, floor) {
        this.name_ = name;
        this.slug_ = Utils.createSlug(name);

        this.floor_ = floor;

        this.sessions_ = [];
    }

    // Gets the name of this location.
    get name() { return this.name_; }

    // Gets the slug of this location that is safe to use in a URL.
    get slug() { return this.slug_; }

    // Gets the floor on which this location is situated.
    get floor() { return this.floor_; }

    // Gets the array of session that will take place in this location.
    get sessions() { return this.sessions_; }

    // Adds |session| to the list of event sessions taking place in this location.
    addSession(session) {
        this.sessions_.push(session);
    }

    // Sorts the sessions known to this location based on their start time, then based on their
    // name. Should be called after all sessions have been added to the location.
    sortSessions() {
        this.sessions_.sort((lhs, rhs) => {
            if (lhs.beginTime === rhs.beginTime)
                return lhs.name.localeCompare(rhs.name);

            return lhs.beginTime > rhs.beginTime ? 1 : -1;
        });
    }

    // Returns whether any of the sessions in the location are visible.
    hasVisibleEvents() {
        for (let session of this.sessions_) {
            if (!session.isHidden())
                return true;
        }

        return false;
    }
}

module.exports = ConventionLocation;
