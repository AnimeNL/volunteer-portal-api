// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

// The Steward class represents an individual Steward who helps out during the
// event itself. They have a name, title, optionally an image and zero or more
// shifts to run during the event.
var Steward = function(schedule, senior, data) {
  this.image_ = data.image || '/images/no-photo.png';
  this.name_ = data.name || '[[undefined]]';
  this.title_ = data.title || '[[undefined]]';
  this.senior_ = senior;
  this.telephone_ = data.telephone || null;

  this.shifts_ = [];

  this.schedule_ = schedule;
};

// Adds a new shift to this Steward's inventory, where they have to be at the
// |event| during |times|, an array having a begin and end date.
Steward.prototype.AddShift = function(event, times) {
  this.shifts_.push({
    event: event,
    begin: new Date(times[0] * 1000),
    end: new Date(times[1] * 1000)
  });
};

// Returns the "slug" of this steward, e.g. their name as it can be used in
// a URL. This is used to generate and match individual Steward pages.
Steward.prototype.GetSlug = function() {
  return this.name_.toLowerCase()
                   .replace(/[^\w ]+/g, '')
                   .replace(/\s+/g, '-');
};

Steward.prototype.GetImage = function() {
  return this.image_;
};

Steward.prototype.GetName = function() {
  return this.name_;
};

Steward.prototype.GetTitle = function() {
  return this.title_;
};

Steward.prototype.GetShifts = function() {
  return this.shifts_;
};

Steward.prototype.IsSenior = function() {
  return this.senior_ == null;
};

Steward.prototype.GetSenior = function() {
  return this.senior_;
};

Steward.prototype.GetTelephone = function() {
  return this.telephone_;
};

Steward.prototype.IsActive = function() {
  return this.GetCurrentShift() == null;
};

Steward.prototype.IsHidden = function() {
  return this.title_ == '_hidden_';
};

Steward.prototype.GetCurrentShift = function() {
  var currentTime = this.schedule_.GetTime(),
      activeShift = null;

  this.shifts_.forEach(function(shift) {
    if (currentTime >= shift.begin.getTime() &&
        currentTime < shift.end.getTime()) {
      activeShift = shift;
    }
  });

  return activeShift;
};

Steward.prototype.GetNextShift = function() {
  var currentTime = this.schedule_.GetTime(),
      nextShift = null;

  this.shifts_.forEach(function(shift) {
    if (currentTime < shift.begin.getTime())
      return;

    if (currentTime >= shift.end.getTime())
      return;

    if (!nextShift || nextShift.begin.getTime() > shift.begin.getTime())
      nextShift = shift;
  });

  return nextShift;
};
