// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// Represents a volunteer for the convention, contains all their basic information and provides
// utility methods to get access to their shifts.
class Volunteer {
    constructor(volunteerData) {
        if (!volunteerData.hasOwnProperty('name') || typeof volunteerData.name !== 'string')
            throw new Error('A volunteer must be assigned a name.');

        this.name_ = volunteerData.name;

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


    ////////////////////////////////////////////////////////////////////////////////////////////////
    // TODO: These methods exist whilst I transition the existing schedule implementation.

    GetTitle() { return 'Remove this.'; }

    GetSenior() { return null; }

    GetSlug() {
        return this.name_.toLowerCase()
                   .replace(/[^\w ]+/g, '')
                   .replace(/\s+/g, '-');
    }

    GetShifts() { return []; }

    GetCurrentShift() { return null; }

    GetNextShift() { return null; }
}

module.exports = Volunteer;
