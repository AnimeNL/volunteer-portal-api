// Copyright 2016 Peter Beverloo. All rights reserved.
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

        if (!volunteerData.hasOwnProperty('photo') || typeof volunteerData.photo !== 'string')
            throw new Error('A volunteer must be assigned a photo.');

        this.photo_ = volunteerData.photo;

        this.telephone_ = null;
        this.hotel_ = null;

        if (volunteerData.hasOwnProperty('telephone') && typeof volunteerData.telephone == 'string')
            this.telephone_ = volunteerData.telephone;

        if (volunteerData.hasOwnProperty('hotel') && typeof volunteerData.hotel == 'string')
            this.hotel_ = volunteerData.hotel;
    }

    // Gets the full name of this volunteer.
    get name() { return this.name_; }

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

    // Returns the current status line of this vlunteer. This could be their level, current schedule
    // or current availability.
    getStatusLine() {
        if (this.staff_)
            return 'Staff';
        else if (this.senior_)
            return 'Senior Steward';  // TODO: Suffix with their role.
        else
            return 'Steward';  // TODO: Use their role instead.
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////
    // TODO: These methods exist whilst I transition the existing schedule implementation.

    GetShifts() { return []; }

    GetCurrentShift() { return null; }

    GetNextShift() { return null; }
}

module.exports = ConventionVolunteer;
