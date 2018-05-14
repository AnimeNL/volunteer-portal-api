// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var EventPage = function(application, parameters) {
  Page.call(this, application);

  this.parameters_ = parameters;

  this.event_ = null;
  this.session_ = null;
  this.schedule_ = null;
  this.timespan_ = null;
};

EventPage.prototype = Object.create(Page.prototype);

// Names of the floors as is to be displayed in the event row.
EventPage.FLOORS = {
  '-1': 'lower ground (Oceans)',
   '0': 'ground floor (Continents)',
   '1': 'first floor (Rivers)',
   '2': 'second floor (Mountains)'
};

EventPage.prototype.PrepareRender = function() {
  var self = this;

  return this.application_.GetSchedule().then(function(schedule) {
    if (self.parameters_.length < 2)
      return;  // no event name in the URL.

    for (var i = 0; i < schedule.events.length; ++i) {
      if (schedule.events[i].slug != self.parameters_[1])
        continue;

      self.event_ = schedule.events[i];
      self.session_ = schedule.events[i].sessions[0];
      self.schedule_ = schedule;

      // The timespan for a given session can be indicated in the URL.
      if (self.parameters_.length > 2) {
        var timespan = self.parameters_[2].split('-');
        if (timespan.length == 2) {
          var beginTime = parseInt(timespan[0], 10),
              endTime = parseInt(timespan[1], 10);

          if (Number.isSafeInteger(beginTime) && Number.isSafeInteger(endTime))
            self.timespan_ = [beginTime, endTime];
        }
      }

      return;
    }
  });
};

EventPage.prototype.BuildSessionRow = function(session) {
  var listContainer = document.createElement('li'),
      dataContainer = document.createElement('div'),
      when = document.createElement('h2'),
      where = document.createElement('p');

  listContainer.className = 'list-item-event list-item-steward-no-pointer';
  listContainer.setAttribute('event-begin', session.beginTime);
  listContainer.setAttribute('event-end', session.endTime);

  // The information contained in this row should be available even after the
  // event has finished, otherwise it won't be displayed anywhere anymore.
  listContainer.setAttribute('event-class-past', 'past-no-collapse');

  when.textContent = DateUtils.format(session.beginTime, DateUtils.FORMAT_SHORT_DAY) + ', ' +
                     DateUtils.format(session.beginTime, DateUtils.FORMAT_SHORT_TIME) + ' until ' +
                     DateUtils.format(session.endTime, DateUtils.FORMAT_SHORT_TIME);

  where.textContent = session.location.name + ', ' + EventPage.FLOORS[session.location.floor];

  dataContainer.className = 'event';

  dataContainer.appendChild(when);
  dataContainer.appendChild(where);

  listContainer.appendChild(dataContainer);

  return listContainer;
};

EventPage.prototype.BuildEmptyStewardRow = function() {
  var listContainer = document.createElement('li');

  listContainer.className = 'list-item-no-content';
  listContainer.innerHTML = '<i>No volunteers have been scheduled for this event.</i>';
  return listContainer;
};

EventPage.prototype.BuildStewardRow = function(steward, beginTime, endTime, highlight) {
  var listContainer = document.createElement('li');
  var image = document.createElement('img'),
      dataContainer = document.createElement('div'),
      name = document.createElement('h2'),
      when = document.createElement('p');

  listContainer.className = 'list-item-steward material-ripple light';
  if (highlight)
    listContainer.className += ' list-item-highlight';

  listContainer.setAttribute('event-begin', beginTime);
  listContainer.setAttribute('event-end', endTime);

  listContainer.setAttribute('handler', true);
  listContainer.setAttribute('handler-navigate', '/volunteers/' + steward.slug + '/');

  image.setAttribute('src', steward.photo);

  var whenPrefix = '';
  if (steward.isSenior())
    whenPrefix = steward.type + ' - ';

  name.textContent = steward.name;
  when.textContent = whenPrefix +
                     DateUtils.format(beginTime, DateUtils.FORMAT_SHORT_DAY) + ', ' +
                     DateUtils.format(beginTime, DateUtils.FORMAT_SHORT_TIME) + ' until ' +
                     DateUtils.format(endTime, DateUtils.FORMAT_SHORT_TIME);

  dataContainer.appendChild(name);
  dataContainer.appendChild(when);

  listContainer.appendChild(image);

  if (steward.isSenior()) {
    var badgeIcon = document.createElement('span');
    badgeIcon.className = 'senior-badge';
    badgeIcon.textContent = '\uE8D0';

    listContainer.appendChild(badgeIcon);
  }

  listContainer.appendChild(dataContainer);

  return listContainer;
};

EventPage.prototype.RenderDetailsLink = function(legacyApplication, container) {
  var element = document.createElement('div'),
      link = document.createElement('a');

  link.textContent = '\uE616';
  link.setAttribute('handler', true);
  link.setAttribute(
    'handler-navigate', '/events/' + this.event_.slug + '/details/');

  element.className = 'event-details';
  element.appendChild(link);

  container.insertBefore(element, container.firstChild);
};

function ShiftInTimespan(shift, timespan) {
  return shift.beginTime < timespan[1] && shift.endTime > timespan[0];
}

EventPage.prototype.OnRender = function(legacyApplication, container, content) {
  if (this.event_ == null)
    return;  // we don't know which event this is.

  var sessionList = content.querySelector('#session-list'),
      stewardList = content.querySelector('#steward-list');

  if (!sessionList || !stewardList)
    return;

  var infoBox = content.querySelector('#info-box');
  if (infoBox && application.content.has(this.event_.id))
    this.RenderDetailsLink(legacyApplication, infoBox);

  var sessionContainer = document.createDocumentFragment(),
      stewardContainer = document.createDocumentFragment(),
      self = this;

  var currentTime = DateUtils.getTime();

  var sessions = this.event_.sessions.slice() /* make a copy */;
  sessions.sort(function(lhs, rhs) {
    if (lhs.endTime <= currentTime && rhs.endTime > currentTime)
      return 1;

    if (lhs.endTime > currentTime && rhs.endTime <= currentTime)
      return -1;

    if (lhs.beginTime != rhs.beginTime)
      return lhs.beginTime > rhs.beginTime ? 1 : -1;

    return 0;
  });

  sessions.forEach(function(session) {
    sessionContainer.appendChild(self.BuildSessionRow(session));
  });

  var stewardShifts = this.event_.shifts.slice() /* make a copy */;
  stewardShifts.sort(function(lhs, rhs) {
    if (self.timespan_) {
      var lhsInTimespan = ShiftInTimespan(lhs, self.timespan_),
          rhsInTimespan = ShiftInTimespan(rhs, self.timespan_);

/**
      if (lhsInTimespan != rhsInTimespan)
        return lhsInTimespan ? -1 : 1;
**/
    }

    if (lhs.endTime <= currentTime && rhs.endTime > currentTime)
      return 1;

    if (lhs.endTime > currentTime && rhs.endTime <= currentTime)
      return -1;

    if (lhs.beginTime != rhs.beginTime)
      return lhs.beginTime > rhs.beginTime ? 1 : -1;

    if (lhs.volunteer.isStaff() && !rhs.volunteer.isStaff())
      return -1;

    if (!lhs.volunteer.isStaff() && rhs.volunteer.isStaff())
      return 1;

    if (lhs.volunteer.isSenior() && !rhs.volunteer.isSenior())
      return -1;

    if (!lhs.volunteer.isSenior() && rhs.volunteer.isSenior())
      return 1;

    return lhs.volunteer.name.localeCompare(rhs.volunteer.name);
  });

  stewardShifts.forEach(function(shift) {
//    var highlight = self.timespan_ && ShiftInTimespan(shift, self.timespan_);
    var highlight = false;

    stewardContainer.appendChild(
        self.BuildStewardRow(shift.volunteer, shift.beginTime, shift.endTime,
                             highlight));
  });

  if (!stewardShifts.length)
    stewardContainer.appendChild(this.BuildEmptyStewardRow());

  sessionList.appendChild(sessionContainer);
  stewardList.appendChild(stewardContainer);
};

EventPage.prototype.ResolveVariable = function(variable) {
  switch(variable) {
    case 'description':
      return this.session_.description;
    case 'name':
      return this.GetTitle();
  }

  // Otherwise we fall through to the parent class' resolve method.
  return Page.prototype.ResolveVariable.call(this);
};

EventPage.prototype.GetTitle = function() {
  return this.session_ ? this.session_.name : 'Oops!';
};

EventPage.prototype.GetTemplate = function() {
  return this.session_ ? 'event-page' : 'event-error-page';
};
