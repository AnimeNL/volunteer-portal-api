// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

// The location object represents a single location suring the event. A location
// can be host to any number of events during the event.
var EventLocation = function(schedule, data) {
  this.name_ = data.name || '[[undefined]]';
  this.floor_ = data.floor || 0;

  this.events_ = [];
  this.schedule_ = schedule;
};

EventLocation.DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

EventLocation.MAX_SIMULTANEOUS_EVENTS = 8;

// Returns the "slug" of this location so that it can be used in a URL.
EventLocation.prototype.GetSlug = function() {
  return this.name_.toLowerCase()
                   .replace(/[^\w ]+/g, '')
                   .replace(/\s+/g, '-');
};

// Adds |event| to the list of events that will take place in this location.
EventLocation.prototype.AddEvent = function(event) {
  this.events_.push(event);
};

EventLocation.prototype.GetName = function() {
  return this.name_;
};

EventLocation.prototype.GetFloor = function() {
  return this.floor_;
};

EventLocation.prototype.GetEvents = function() {
  return this.events_;
};

EventLocation.prototype.HasVisibleEvents = function() {
  for (var i = 0; i < this.events_.length; ++i) {
    if (!this.events_[i].IsHidden())
      return true;
  }

  return false;
};

EventLocation.prototype.GetUpcomingEvents = function(count, include_hidden) {
  var currentTime = this.schedule_.GetTime(),
      upcomingEvents = [];

  function DateToDisplayTime(date) {
    return EventLocation.DAYS[date.getDay()] + ' ' +
           date.toTimeString().match(/\d{2}:\d{2}/)[0];
  }

  for (var i = 0; i < this.events_.length; ++i) {
    if (this.events_[i].IsHidden() && !include_hidden)
      continue;

    var sessions = this.events_[i].GetSessions();
    for (var j = 0; j < sessions.length; ++j) {
      if (sessions[j].end.getTime() < currentTime)
        continue;

      var active = sessions[j].begin.getTime() <= currentTime;
      var times = DateToDisplayTime(sessions[j].begin) + ' - ' +
                  DateToDisplayTime(sessions[j].end);

      upcomingEvents.push({
        name: this.events_[i].GetName(),
        begin: sessions[j].begin.getTime(),
        time: times,
        active: active
      });
    }

    if (upcomingEvents.length > EventLocation.MAX_SIMULTANEOUS_EVENTS)
      break;
  }

  upcomingEvents.sort(function(lhs, rhs) {
    return lhs.begin > rhs.begin ? 1 : -1;
  });

  return upcomingEvents.slice(0, 2);
};
