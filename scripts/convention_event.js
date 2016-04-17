// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// Represents one of the events that happens at the convention. Will store all related information,
// and has convenience methods for retrieving the location and stewards associated with it.
class ConventionEvent {
    constructor(eventData, location) {
        if (!eventData.hasOwnProperty('name') || typeof eventData.name !== 'string')
            throw new Error('An event must be assigned a name.');

        this.name_ = eventData.name;
        this.description_ = eventData.description || null;

        if (!eventData.hasOwnProperty('hidden') || typeof eventData.hidden !== 'boolean')
            throw new Error('An event must have a visibility indicator.');

        this.hidden_ = eventData.hidden;

        if (!eventData.hasOwnProperty('begin') || typeof eventData.begin !== 'number' ||
            !eventData.hasOwnProperty('end') || typeof eventData.end !== 'number') {
            throw new Error('An event must have a known begin and end time.');
        }

        this.begin_ = new Date(eventData.begin * 1000);
        this.end_ = new Date(eventData.end * 1000);

        this.location_ = location;

        // Register this event as taking place in the |location|.
        location.addEvent(this);
    }

    // Gets the name of this event.
    get name() { return this.name_; }

    // Gets the description of this event. May be NULL.
    get description() { return this.description_; }

    // Gets the Date object representing the begin time of this event.
    get begin() { return this.begin_; }

    // Gets the UNIX timestamp at microsecond granularity of the event's begin time.
    get beginTime() { return this.begin_.getTime(); }

    // Gets the Date object representing the end time of this event.
    get end() { return this.end_; }

    // Gets the UNIX timestamp at microsecond granularity of the event's end time.
    get endTime() { return this.end_.getTime(); }

    // Gets the location in which this event will be taking place.
    get location() { return this.location_; }

    // Returns whether this event is hidden. Hidden events may not be available for all users.
    isHidden() { return this.hidden_; }
}

module.exports = ConventionEvent;
