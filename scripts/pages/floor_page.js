// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var FloorPage = function(application, parameters) {
  Page.call(this, application);

  this.schedule_ = null;

  this.floor_ = 'rivers';
  if (parameters[1] in FloorPage.FLOORS)
    this.floor_ = parameters[1];
};

FloorPage.prototype = Object.create(Page.prototype);

// Number of upcoming events to display for any given room.
FloorPage.EVENT_COUNT = 2;

// The floor plan of the World Forum convention center.
FloorPage.FLOORS = {
  oceans: -1,
  continents: 0,
  rivers: 1,
  mountains: 2
};

// Builds an event entry for the current and next events for a location. If
// |event| is NULL, a no-event row will be created instead.
FloorPage.prototype.BuildEventRow = function(upcomingEvent) {
  var container = document.createElement('li');
  if (!upcomingEvent) {
    container.className = 'list-item-no-content';
    container.innerHTML = '<i>No more events have been scheduled in this room.</i>';

    return container;
  }

  var time = document.createElement('p'),
      name = document.createElement('p');

  container.className = 'list-item-room-event';
  if (upcomingEvent.active)
    container.className += ' active';

  time.className = 'time';
  if (upcomingEvent)
    time.textContent = upcomingEvent.time;

  name.className = 'event';
  if (upcomingEvent)
    name.textContent = upcomingEvent.name;
  else
    name.innerHTML = '<i>No scheduled events.</i>';

  if (upcomingEvent)
    container.appendChild(time);
  container.appendChild(name);
  return container;
};

// Builds a card for |location|, to be displayed in the room overview for the
// current floor. An icon will indicate if Stewards are currently active there.
FloorPage.prototype.BuildRoomCard = function(location) {
  var container = document.createElement('div'),
      header = document.createElement('h3'),
      eventList = document.createElement('ol'),
      footer = document.createElement('footer'),
      self = this;

  container.className = 'material-card pointer material-card-bottom-spacing';
  if (location.GetName().indexOf('Neil') !== -1)
    container.className += ' room-neil';

  // Make it possible to click on the card to go to the room overview.
  container.setAttribute('handler', true);
  container.setAttribute('handler-navigate',
      '/floors/' + this.floor_ + '/' + location.GetSlug() + '/');

  header.textContent = location.GetName();

  eventList.className = 'material-list list-room-event';

  var upcomingEvents = location.GetUpcomingEvents(
      FloorPage.EVENT_COUNT, this.application_.GetUser().getOption('hidden_events', false));

  upcomingEvents.forEach(function(event) {
    eventList.appendChild(self.BuildEventRow(event));
  });

  if (!upcomingEvents.length) {
    eventList.appendChild(self.BuildEventRow());
    container.classList.add('room-finished');
  }

  footer.textContent = 'Full schedule';

  // Put them all together and return the container.
  container.appendChild(header);
  container.appendChild(eventList);
  container.appendChild(footer);

  return { name: location.GetName(),
           has_events: !!upcomingEvents.length,
           node: container };
};

// Ensures that the schedule is available prior to rendering this page.
FloorPage.prototype.PrepareRender = function() {
  return this.application_.GetSchedule().then(function(schedule) {
    this.schedule_ = schedule;

  }.bind(this));
};

// Generates a list of the rooms on this floor, including the currently active
// event and the upcoming event, for quick and convenient overview of stuff.
FloorPage.prototype.OnRender = function(application, container, content) {
  var roomOverview = document.createDocumentFragment(),
      currentFloor = FloorPage.FLOORS[this.floor_],
      locations = this.schedule_.GetLocations(),
      self = this;

  var include_hidden = application.GetUser().getOption('hidden_events', false),
      rendered_locations = [];

  locations.forEach(function(location) {
    if (!location.HasVisibleEvents() && !include_hidden)
      return;

    if (location.GetFloor() != currentFloor)
      return;

    rendered_locations.push(self.BuildRoomCard(location));
  });

  rendered_locations.sort(function(lhs, rhs) {
    if (lhs.has_events == rhs.has_events)
      return lhs.name.localeCompare(rhs.name);

    return lhs.has_events ? -1 : 1;
  });

  rendered_locations.forEach(function(location) {
    roomOverview.appendChild(location.node);
  });

  var listContainer = content.querySelector('#room-cards');
  if (listContainer)
    listContainer.appendChild(roomOverview);
};

// Resolves |variable| against our local state. This allows us to use content
// handlers specific to this page without needing to pollute global state.
FloorPage.prototype.ResolveVariable = function(variable) {
  switch(variable) {
    case 'image':
      return '/images/icon-' + this.floor_ + '.jpg';
    case 'name':
      return this.GetTitle();
    case 'floor':
      switch (this.floor_) {
        case 'oceans':      return 'Lower ground';
        case 'continents':  return 'Ground floor';
        case 'rivers':      return 'First floor';
        case 'mountains':   return 'Second floor';
      }
  }

  // Otherwise we fall through to the parent class' resolve method.
  return Page.prototype.ResolveVariable.call(this);
};

// Returns the title of the floor overview page. The first character will
// be capitalized so that it looks nicer.
FloorPage.prototype.GetTitle = function() {
  return this.floor_[0].toUpperCase() + this.floor_.slice(1);
};

FloorPage.prototype.GetTemplate = function() {
  return 'floors-page';
};
