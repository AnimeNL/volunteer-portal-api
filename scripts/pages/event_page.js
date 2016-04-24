// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var EventPage = function(application, parameters) {
  Page.call(this, application);

  this.parameters_ = parameters;
  
  this.event_ = null;
  this.schedule_ = null;
};

EventPage.prototype = Object.create(Page.prototype);

// Fully written out names of the days in the week, starting on Sunday.
EventPage.DAYS = [
  'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'
];

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

    var events = schedule.GetEvents(),
        eventId = parseInt(self.parameters_[1].split('-')[0], 10);

    if (isNaN(eventId))
        return;  // invalid event id in the URL.

    if (eventId < 0 || eventId >= events.length)
        return;  // invalid event id in the URL.

    if (events[eventId].GetSlug() != self.parameters_[1])
        return;  // event name has been invalidated?

    self.event_ = events[eventId];
    self.schedule_ = schedule;
  });
};

EventPage.prototype.DateToDisplayTime = function(beginDate, endDate) {
  return EventPage.DAYS[beginDate.getDay()] + ', from ' +
             beginDate.toTimeString().match(/\d{2}:\d{2}/)[0] + ' until ' +
             endDate.toTimeString().match(/\d{2}:\d{2}/)[0] + '.';
};

EventPage.prototype.BuildSessionRow = function(session, currentTime) {
  var listContainer = document.createElement('li'),
      dataContainer = document.createElement('div'),
      when = document.createElement('h2'),
      where = document.createElement('p');

  listContainer.className = 'list-item-event list-item-steward-no-pointer';
  listContainer.setAttribute('event-begin', session.begin.getTime());
  listContainer.setAttribute('event-end', session.end.getTime());

  // The information contained in this row should be available even after the
  // event has finished, otherwise it won't be displayed anywhere anymore.
  listContainer.setAttribute('event-class-past', 'past-no-collapse');

  function DateToDisplayTime(date) {
    return EventPage.DAYS[date.getDay()] + ' ' +
           date.toTimeString().match(/\d{2}:\d{2}/)[0];
  }

  when.textContent = DateToDisplayTime(session.begin) + ' until ' +
                     DateToDisplayTime(session.end);

  where.textContent = this.event_.location.name + ', ' +
        EventPage.FLOORS[this.event_.location.floor];

  dataContainer.className = 'event';

  dataContainer.appendChild(when);
  dataContainer.appendChild(where);

  listContainer.appendChild(dataContainer);

  return listContainer;
};

EventPage.prototype.BuildStewardRow = function(steward, beginDate, endDate, currentTime) {
  var listContainer = document.createElement('li');
  if (!steward) {
    listContainer.className = 'list-item-no-content';
    listContainer.innerHTML = '<i>No stewards have been scheduled for this event.</i>';
    return listContainer;
  }

  var image = document.createElement('img'),
      dataContainer = document.createElement('div'),
      name = document.createElement('h2'),
      when = document.createElement('p');

  listContainer.className = 'list-item-steward material-ripple light';
  listContainer.setAttribute('event-begin', beginDate.getTime());
  listContainer.setAttribute('event-end', endDate.getTime());

  listContainer.setAttribute('handler', true);
  listContainer.setAttribute('handler-navigate',
      '/stewards/' + steward.slug + '/');

  image.setAttribute('src', steward.photo);

  function DateToDisplayTime(date) {
    return date.toTimeString().match(/\d{2}:\d{2}/)[0];
  }

  name.textContent = steward.name;

  var whenPrefix = document.createElement('span');
      whenContent = EventPage.DAYS[beginDate.getDay()] + ', from ' +
                    DateToDisplayTime(beginDate) + ' until ' +
                    DateToDisplayTime(endDate) + '.';

  if (steward.IsSenior()) {
    whenPrefix.textContent = steward.getStatusLine().split(' ')[0];
    whenContent = ' - ' + whenContent;
  }

  when.appendChild(whenPrefix);
  when.appendChild(document.createTextNode(whenContent));

  dataContainer.appendChild(name);
  dataContainer.appendChild(when);

  listContainer.appendChild(image);
  listContainer.appendChild(dataContainer);

  return listContainer;
};

EventPage.prototype.OnRender = function(application, container, content) {
  if (this.event_ == null)
    return;  // we don't know which event this is.

  var sessionList = content.querySelector('#session-list'),
      stewardList = content.querySelector('#steward-list'),
      description = content.querySelector('#event-description');

  if (!sessionList || !stewardList || !description)
    return;

  var sessionContainer = document.createDocumentFragment(),
      stewardContainer = document.createDocumentFragment(),
      currentTime = DateUtils.getTime(),
      self = this;

  this.event_.GetSessions().forEach(function(session) {
    sessionContainer.appendChild(self.BuildSessionRow(session, currentTime));
  });

  var stewardShifts = this.event_.GetShifts();
  stewardShifts.forEach(function(shift) {
    stewardContainer.appendChild(self.BuildStewardRow(shift.steward,
                                                      shift.begin, shift.end,
                                                      currentTime));
  });

  if (!stewardShifts.length)
    stewardContainer.appendChild(this.BuildStewardRow());

  sessionList.appendChild(sessionContainer);
  stewardList.appendChild(stewardContainer);

  description.innerHTML = this.event_.GetDescription();
};

EventPage.prototype.ResolveVariable = function(variable) {
  switch(variable) {
    case 'name':
      return this.GetTitle();
  }

  // Otherwise we fall through to the parent class' resolve method.
  return Page.prototype.ResolveVariable.call(this);
};

EventPage.prototype.GetTitle = function() {
  return this.event_ ? this.event_.GetName() : 'Oops!';
};

EventPage.prototype.GetTemplate = function() {
  return this.event_ ? 'event-page' : 'event-error-page';
};
