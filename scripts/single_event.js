// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

// This class represents a single event happening at the convention, where it
// must be noted that a single event still can have multiple sessions. An event
// has a location, a name and one or more sessions. Additionally, convenience
// accessors will be available to find the stewards required for this event.
var SingleEvent = function(schedule, data, id, location) {
  this.location_ = location;
  this.id_ = id;

  this.name_ = data.name || '[[undefined]]';
  this.description_ = data.description || '<i>No description.</i>';
  this.sessions_ = [];

  this.shifts_ = [];
  this.shifts_invalidated_ = false;

  this.hidden_ = data.hidden || false;

  var self = this;
  data.times.sort(function(lhs, rhs) {
    return lhs[0] > rhs[0] ? 1 : -1;
  });

  data.times.forEach(function(range) {
    self.sessions_.push({
      begin: new Date(range[0] * 1000),
      end: new Date(range[1] * 1000)
    });
  });
};

// Adds a new shift to this event, where |steward| has to be present at |times|.
SingleEvent.prototype.AddShift = function(steward, times) {
  this.shifts_invalidated_ = true;
  this.shifts_.push({
    steward: steward,
    begin: new Date(times[0] * 1000),
    end: new Date(times[1] * 1000)
  });
};

// Returns the "slug" of this event so that it can be used in a URL.
SingleEvent.prototype.GetSlug = function() {
  return this.id_ + '-' +
      this.name_.toLowerCase()
                .replace(/[^\w ]+/g, '')
                .replace(/\s+/g, '-');
};

SingleEvent.prototype.GetLocation = function() {
  return this.location_;
};

SingleEvent.prototype.GetName = function() {
  return this.name_;
};

SingleEvent.prototype.GetDescription = function() {
  return this.description_;
};

SingleEvent.prototype.GetSessions = function() {
  return this.sessions_;
};

SingleEvent.prototype.GetShifts = function() {
  if (this.shifts_invalidated_) {
    this.shifts_invalidated_ = false;

    var currentTime = GetCurrentDate().getTime();
    this.shifts_.sort(function(lhs, rhs) {
      var lhs_end_time = lhs.end.getTime(),
          rhs_end_time = rhs.end.getTime();

      if (lhs_end_time < currentTime && rhs_end_time >= currentTime)
        return 1;
      if (lhs_end_time >= currentTime && rhs_end_time < currentTime)
        return -1;

      var lhs_begin_time = lhs.begin.getTime(),
          rhs_begin_time = rhs.begin.getTime();

      if (lhs_begin_time > rhs_begin_time)
        return 1;
      if (lhs_begin_time < rhs_begin_time)
        return -1;

      return lhs.steward.GetName().localeCompare(rhs.steward.GetName());
    });
  }

  return this.shifts_;
};

SingleEvent.prototype.GetShiftsForTime = function(time) {
  var shifts = [];
  this.shifts_.forEach(function(shift) {
    if (shift.begin.getTime() <= time && shift.end.getTime() > time)
      shifts.push(shift);
  });

  return shifts;
};

SingleEvent.prototype.GetNavigateLocation = function() {
  return '/events/' + this.GetSlug() + '/';
};

// Returns if one of this event's sessions occurs during |time|.
SingleEvent.prototype.InProgressOnTime = function(time) {
  var sessionFound = false;

  this.sessions_.forEach(function(session) {
    sessionFound |= session.begin.getTime() <= time &&
                    session.end.getTime() > time;
  });

  return sessionFound;
};

SingleEvent.prototype.IsHidden = function() {
  return this.hidden_;
};
