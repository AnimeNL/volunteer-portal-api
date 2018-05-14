// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const Utils = require('./utils');

// Represents a volunteer for the convention, contains all their basic information and provides
// utility methods to get access to their shifts.
class ConventionVolunteer {
    constructor(volunteerData) {
        if (!volunteerData.hasOwnProperty('name') || typeof volunteerData.name !== 'string')
            throw new Error('A volunteer must be assigned a name.');

        this.name_ = volunteerData.name;
        this.slug_ = Utils.createSlug(this.name_);

        if (!volunteerData.hasOwnProperty('group') || typeof volunteerData.group !== 'string')
            throw new Error('A volunteer must be assigned a group.');

        this.group_ = volunteerData.group;

        if (!volunteerData.hasOwnProperty('type') || typeof volunteerData.type !== 'string')
            throw new Error('A volunteer must be assigned a type.');

        this.staff_ = false;
        this.senior_ = false;

        switch (volunteerData.type) {
            case 'Staff':
                this.staff_ = true;
                /* deliberate fall-through */
            case 'Senior':
                this.senior_ = true;
                /* deliberate fall-through */
            case 'Volunteer':
                break;
            default:
                throw new Error('Invalid volunteer type: ' + volunteerData.type);
        }

        this.type_ = volunteerData.type;
        this.title_ = volunteerData.title || volunteerData.type;

        if (!volunteerData.hasOwnProperty('photo') || typeof volunteerData.photo !== 'string')
            throw new Error('A volunteer must be assigned a photo.');

        this.photo_ = volunteerData.photo;

        this.telephone_ = null;
        this.hotel_ = null;

        if (volunteerData.hasOwnProperty('telephone') && typeof volunteerData.telephone == 'string')
            this.telephone_ = volunteerData.telephone;

        if (volunteerData.hasOwnProperty('hotel') && typeof volunteerData.hotel == 'string')
            this.hotel_ = volunteerData.hotel;

        this.unavailable_ = [];
        this.shifts_ = [];
    }

    // Gets the full name of this volunteer.
    get name() { return this.name_; }

    // Gets the type of volunteer, which is one of {Volunteer, Senior, Staff}.
    get type() { return this.type_; }

    // Gets the group this volunteer is part of.
    get group() { return this.group_; }

    // Gets the title of this volunteer, specific to their environment.
    get title() { return this.title_; }

    // Gets the slug of this volunteer, using which they can be identified in a URL.
    get slug() { return this.slug_; }

    // Gets the URL to a photo representing this volunteer.
    get photo() { return this.photo_; }

    // Gets the telephone number of this volunteer. May be NULL.
    get telephone() { return this.telephone_; }

    // Gets the hotel this volunteer will be staying in. May be NULL.
    get hotel() { return this.hotel_; }

    // Returns whether this volunteer is a senior member of the group.
    isSenior() { return this.senior_; }

    // Returns whether this volunteer is a staff member of the group.
    isStaff() { return this.staff_; }

    // Adds the time span between |beginTime| and |endTime| as time where this volunteer is not
    // available. They will appear greyed out in the interface of the application.
    addUnavailableTime(beginTime, endTime) {
        this.unavailable_.push({ beginTime, endTime });
    }

    // Returns whether the volunteer is available at |time|. They may be on a shift.
    isAvailable(time) {
        for (let i = 0; i < this.unavailable_.length; ++i) {
            if (this.unavailable_[i].beginTime <= time && this.unavailable_[i].endTime > time)
                return false;
        }

        return true;
    }

    // Adds a shift on |event| that has a time span between |beginTime| and |endTime|.
    addShift(event, beginTime, endTime) {
        this.shifts_.push({ event, beginTime, endTime });
    }

    // Gets the shifts that this volunteer will be attending.
    get shifts() { return this.shifts_; }

    // Gets the current or soonest upcoming shift for the volunteer.
    getCurrentOrUpcomingShift(time) {
        for (let i = 0; i < this.shifts_.length; ++i) {
            if (this.shifts_[i].endTime < time)
                continue;  // the shift is in the past

            let shift = this.shifts_[i];
            shift.current = shift.beginTime < time;

            return shift;
        }

        return null;
    }
}

module.exports = ConventionVolunteer;
