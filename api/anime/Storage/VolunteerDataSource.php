<?php
// Copyright 2020 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Storage;

// The VolunteerDataSource interface defines the operations that must be supported by a data source
// that backs the volunteer database.
interface VolunteerDataSource {
    // Returns an array of VolunteerRegistration objects that provide access to each of the
    // volunteer registrations which have been received so far.
    public function getRegistrations() : array;
}
