// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const Utils = require('./utils');

// Represents one of the events that happens at the convention. Will store all related information,
// and has convenience methods for retrieving the location and stewards associated with it.
class ConventionEventSession {
    constructor(sessionData, event, location) {
        if (!sessionData.hasOwnProperty('name') || typeof sessionData.name !== 'string')
            throw new Error('A session must be assigned a name.');

        this.name_ = sessionData.name;
        this.description_ = sessionData.description || null;

        if (!sessionData.hasOwnProperty('begin') || typeof sessionData.begin !== 'number' ||
            !sessionData.hasOwnProperty('end') || typeof sessionData.end !== 'number') {
            throw new Error('A session must have a known begin and end time.');
        }

        this.begin_ = new Date(sessionData.begin * 1000);
        this.end_ = new Date(sessionData.end * 1000);

        this.event_ = event;
        this.location_ = location;

        // Register this session as taking place in the |location|.
        location.addSession(this);
    }

    // Gets the name of this session.
    get name() { return this.name_; }

    // Gets the description of this session. May be NULL.
    get description() { return this.description_; }

    // Gets the Date object representing the begin time of this session.
    get begin() { return this.begin_; }

    // Gets the UNIX timestamp at microsecond granularity of the session's begin time.
    get beginTime() { return this.begin_.getTime(); }

    // Gets the Date object representing the end time of this session.
    get end() { return this.end_; }

    // Gets the UNIX timestamp at microsecond granularity of the session's end time.
    get endTime() { return this.end_.getTime(); }

    // Gets the event that this session is part of.
    get event() { return this.event_; }

    // Gets the location in which this session will be taking place.
    get location() { return this.location_; }

    // Returns whether this session is hidden. Hidden events may not be available for all users.
    isHidden() { return this.event_.hidden_; }

    // Returns whether this session is currently in progress.
    isActive() {
        const currentTime = Utils.getTime();

        return this.begin_.getTime() <= currentTime && this.end_.getTime() > currentTime;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////
    // TODO: These methods exist whilst I transition the existing schedule implementation.

    getFormattedTime() {
        return Utils.formatDisplayTime(this.begin_) + ' - ' + Utils.formatDisplayTime(this.end_);
    }
}

module.exports = ConventionEventSession;
