// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

// The schedule represents the schedule of the events, containing all the
// stewards, locations, events and shifts. The data can be cross-referenced
// with each other using convenience functions on each of the objects.
var Schedule = function() {
  this.hash_ = '';
  this.locations_ = [];
  this.events_ = [];
  this.stewards_ = [];
};

Schedule.prototype.CheckForScheduleUpdate = function() {
  var request = new XMLHttpRequest();
  request.open('get', '/schedule.json?live', true);
  request.addEventListener('load', function() {
    try {
      var schedule = JSON.parse(request.responseText);
      if (!schedule.hasOwnProperty('hash') || this.hash_ == schedule.hash)
        return;

      // In-place install the new schedule, and display a temporary snackbar
      // to inform the user that the update has commenced.

      this.Initialize(schedule, true /* update_cache */);
      this.DisplayUpdateBanner();

      window.application.Navigate();

    } catch (e) {}

  }.bind(this));

  request.send();
};

Schedule.prototype.InitializeFromCache = function() {
  try {
    var cached_schedule = this.ReadCachedSchedule();
    if (!cached_schedule)
      return false;

    this.Initialize(cached_schedule, false /* update_cache */);
    return true;

  } catch (e) {}

  return false;
};

Schedule.prototype.Initialize = function(unchecked_schedule, update_cache) {
  unchecked_schedule = unchecked_schedule || {};

  var schedule = {
    hash: unchecked_schedule.hash || '' + Date.now(),
    locations: unchecked_schedule.locations || [],
    events: unchecked_schedule.events || [],
    stewards: unchecked_schedule.stewards || [],
    shifts: unchecked_schedule.shifts || []
  };

  this.hash_ = schedule.hash;

  this.locations_ = [];
  this.events_ = [];
  this.stewards_ = [];

  var self = this;
  schedule.locations.forEach(function(data) {
    self.locations_.push(new EventLocation(self, data));
  });

  // Load the event's events from |schedule|. The ordering does not matter.
  schedule.events.forEach(function(data) {
    var location = null;
    if ('location' in data)
      location = self.locations_[data.location];

    var event = new SingleEvent(self, data, self.events_.length, location);
    if (location)
      location.AddEvent(event);

    self.events_.push(event);
  });

  // Load the event's stewards from the |schedule| object.
  schedule.stewards.forEach(function(data) {
    var senior = data.senior === null ? null : self.stewards_[data.senior];
    self.stewards_.push(new Steward(self, senior, data));
  });

  // Load the shifts for the stewards of this event. The shifts will be added
  // twice, once to the event they're part of, once to the Steward who will
  // be running the shift.
  schedule.shifts.forEach(function(data) {
    self.events_[data.event].AddShift(self.stewards_[data.steward], data.times);
    self.stewards_[data.steward].AddShift(self.events_[data.event], data.times);
  });

  // The schedule has been initialized by this point. Update the local cache
  // with the latest information.
  if (update_cache)
    this.WriteCachedSchedule(schedule);

  return this;
};

Schedule.prototype.DisplayUpdateBanner = function() {
  var update_banner = document.createElement('div');
  update_banner.className = 'update-banner hidden';
  update_banner.textContent = 'The schedule has been updated!';

  requestAnimationFrame(function() {
    setTimeout(function() { update_banner.classList.remove('hidden'); }, 0);
    setTimeout(function() {
      update_banner.classList.add('hidden');
      setTimeout(function() {
        document.body.removeChild(update_banner);

      }, 1000);
    }, 3000);
  });

  document.body.appendChild(update_banner);
};

// Returns the in-progress events at the current time.
Schedule.prototype.GetCurrentEvents = function(include_hidden) {
  var current_time = this.GetTime(),
      current_events = {
    '-1': [],
       0: [],
       1: [],
       2: []
  };

  // Iterate over each of the events to find ones that are currently active.
  this.events_.forEach(function(event) {
    if (event.IsHidden() && !include_hidden)
      return;  // the event is hidden.

    if (!event.InProgressOnTime(current_time))
      return;  // the event is not in progress.

    current_events[event.GetLocation().GetFloor()].push(event);
  });

  // TODO(peter): Sort the current events?
  return current_events;
};

// Returns the current time. For development purposes, the current time may
// actually be set to something else.
Schedule.prototype.GetTime = function() {
  return GetCurrentDate().getTime();
};

Schedule.prototype.GetEvents = function() {
  return this.events_;
};

Schedule.prototype.GetLocations = function() {
  return this.locations_;
};

Schedule.prototype.GetSteward = function(name) {
  var found_steward = null;
  this.stewards_.forEach(function(steward) {
    if (steward.GetName() == name)
      found_steward = steward;
  });

  return found_steward;
};

Schedule.prototype.GetStewards = function() {
  return this.stewards_;
};

// Returns a JSON version of the schedule from local cache, or NULL.
Schedule.prototype.ReadCachedSchedule = function() {
  var schedule = localStorage['_schedule_cache'];
  if (!schedule || typeof schedule != 'string' || schedule.length <= 16)
    return null;

  return JSON.parse(schedule);
};

// |schedule| is the JSON-representation of the schedule.
Schedule.prototype.WriteCachedSchedule = function(schedule) {
  localStorage['_schedule_cache'] = JSON.stringify(schedule);
};
