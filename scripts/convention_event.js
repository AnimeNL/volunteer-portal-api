// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// Represents a unique event during the convention. An event has a `hidden` flag that is unique for
// the event with all its sessions, and one or more sessions that take place as part of it.
class ConventionEvent {
    constructor(hidden) {
        this.hidden_ = hidden;
        this.sessions_ = [];
    }

    // Gets the sessions that are part of this event.
    get sessions() { return this.sessions_; }

    // Returns whether this event is hidden. Hidden events may not be available for all users.
    isHidden() { return this.hidden_; }
}

module.exports = ConventionEvent;
