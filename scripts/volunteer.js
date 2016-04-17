// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// Creates a slug out of |name|. Correctly handles a series of accents that were silently dropped
// in the previous version of the volunteer portal.
function createSlug(name) {
    const replacements = { 'à': 'a', 'á': 'a', 'â': 'a', 'ã': 'a', 'ä': 'a', 'å': 'a', 'ò': 'o',
                           'ó': 'o', 'ô': 'o', 'õ': 'o', 'ö': 'o', 'ø': 'o', 'è': 'e', 'é': 'e',
                           'ê': 'e', 'ë': 'e', 'ð': 'o', 'ç': 'c', 'ì': 'i', 'í': 'i', 'î': 'i',
                           'ï': 'i', 'ù': 'u', 'ú': 'u', 'û': 'u', 'ü': 'u', 'ñ': 'u', 'š': 's',
                           'ÿ': 'y', 'ý': 'y' };

    let slug = '';
    for (let i = 0; i < name.length; ++i) {
        const character = name[i].toLowerCase();

        if (replacements.hasOwnProperty(character))
            slug += replacements[character];
        else
            slug += character;
    }

    return slug.replace(/[^\w ]+/g, '')
               .replace(/\s+/g, '-');
}

// Represents a volunteer for the convention, contains all their basic information and provides
// utility methods to get access to their shifts.
class Volunteer {
    constructor(volunteerData) {
        if (!volunteerData.hasOwnProperty('name') || typeof volunteerData.name !== 'string')
            throw new Error('A volunteer must be assigned a name.');

        this.name_ = volunteerData.name;
        this.slug_ = createSlug(this.name_);

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


    ////////////////////////////////////////////////////////////////////////////////////////////////
    // TODO: These methods exist whilst I transition the existing schedule implementation.

    GetTitle() { return 'Remove this.'; }

    GetShifts() { return []; }

    GetCurrentShift() { return null; }

    GetNextShift() { return null; }
}

module.exports = Volunteer;
