// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const DateUtils = require('./date_utils');

// Represents one of the events that happens at the convention. Will store all related information,
// and has convenience methods for retrieving the location and stewards associated with it.
class ConventionEventSession {
    constructor(sessionData, event, location) {
        if (!sessionData.hasOwnProperty('name') || typeof sessionData.name !== 'string')
            throw new Error('A session must be assigned a name.');

        this.name_ = sessionData.name;
        this.description_ = sessionData.description || 'No description';

        if (!sessionData.hasOwnProperty('begin') || typeof sessionData.begin !== 'number' ||
            !sessionData.hasOwnProperty('end') || typeof sessionData.end !== 'number') {
            throw new Error('A session must have a known begin and end time.');
        }

        this.beginTime_ = sessionData.begin * 1000;
        this.endTime_ = sessionData.end * 1000;

        this.event_ = event;
        this.location_ = location;

        // Register this session as taking place in the |location|.
        location.addSession(this);
    }

    // Gets the name of this session.
    get name() { return this.name_; }

    // Gets the description of this session. May be NULL.
    get description() { return this.description_; }

    // Gets the UNIX timestamp at microsecond granularity of the session's begin time.
    get beginTime() { return this.beginTime_; }

    // Gets the UNIX timestamp at microsecond granularity of the session's end time.
    get endTime() { return this.endTime_; }

    // Gets the event that this session is part of.
    get event() { return this.event_; }

    // Gets the location in which this session will be taking place.
    get location() { return this.location_; }

    // Returns whether this session is hidden. Hidden events may not be available for all users.
    isHidden() { return this.event_.hidden_; }

    // Returns whether this session is currently in progress.
    isActive() {
        const currentTime = DateUtils.getTime();

        return this.beginTime_ <= currentTime && this.endTime_ > currentTime;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////
    // TODO: These methods exist whilst I transition the existing schedule implementation.

    getFormattedTime() {
        return DateUtils.format(this.beginTime_, DateUtils.FORMAT_DAY_SHORT_TIME) + ' - ' +
               DateUtils.format(this.endTime_, DateUtils.FORMAT_DAY_SHORT_TIME);
    }
}

module.exports = ConventionEventSession;
