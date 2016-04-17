// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// Represents one of the locations in which events will take place during the convention. It has an
// associated name and floor, and provides convenience methods for the events taking place in it.
class ConventionLocation {
    constructor(name, floor) {
        this.name_ = name;
        this.floor_ = floor;

        this.events_ = [];
    }

    // Gets the name of this location.
    get name() { return this.name_; }

    // Gets the floor on which this location is situated.
    get floor() { return this.floor_; }

    // Gets the array of events that will take place in this location.
    get events() { return this.events_; }

    // Adds |event| to the list of events taking place in this location.
    addEvent(event) {
        this.events_.push(event);
    }
}

module.exports = ConventionLocation;
