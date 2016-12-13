// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const Utils = require('./utils');

// Represents a unique event during the convention. An event has a `hidden` flag that is unique for
// the event with all its sessions, and one or more sessions that take place as part of it.
class ConventionEvent {
    constructor(id, hidden) {
        this.id_ = id;
        this.hidden_ = hidden;
        this.sessions_ = [];
        this.shifts_ = [];
        this.slug_ = null;
    }

    // Gets the ID that has been assigned to this event.
    get id() { return this.id_; }

    // Gets the slug through which this event can be navigated to.
    get slug() {
        if (!this.slug_)
            this.slug_ = this.id_ + '-' + Utils.createSlug(this.sessions_[0].name);

        return this.slug_;
    }

    // Gets the sessions that are part of this event.
    get sessions() { return this.sessions_; }

    // Returns whether this event is hidden. Hidden events may not be available for all users.
    isHidden() { return this.hidden_; }

    // Returns the session that will be active at |time|. If no session is active at that time, the
    // first session of the event will be returned.
    getSessionForTime(time) {
        const finalSession = this.sessions_[this.sessions_.length - 1];

        for (let i = 0; i < this.sessions_.length; ++i) {
            if (this.sessions_[i].beginTime >= time && this.sessions_[i].endTime < time)
                return this.sessions_[i];  // exact match

            if (this.sessions_[i].endTime < time)
                continue;  // the session is in the past

            if (this.sessions_[i].beginTime < finalSession.beginTime)
                return this.sessions_[i];  // session that's nearest in the future
        }

        return finalSession;
    }

    // Adds a shift for |volunteer| to this event that will run between |beginTime| and |endTime|.
    addShift(volunteer, beginTime, endTime) {
        this.shifts_.push({ volunteer, beginTime, endTime });
    }

    // Gets the shifts that have been associated with this event.
    get shifts() { return this.shifts_; }
}

module.exports = ConventionEvent;
