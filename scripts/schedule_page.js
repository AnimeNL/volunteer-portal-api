// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var SchedulePage = function(application) {
  Page.call(this, application);

  this.schedule_ = null;
};

SchedulePage.prototype = Object.create(Page.prototype);

// Fully written out names of the days in the week, starting on Sunday.
SchedulePage.DAYS = [
  'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'
];

// Returns a promise that resolves as soon as the schedule is available and
// has been cached locally for further use.
SchedulePage.prototype.PrepareRender = function() {
  var self = this;

  return this.application_.GetSchedule().then(function(schedule) {
    self.schedule_ = schedule;
    return schedule;
  });
};

// Image visualizing the entity for whom this schedule is being generated.
SchedulePage.prototype.GetImage = function() { return null; }

// Name of the entity for whom this schedule is being generated.
SchedulePage.prototype.GetName = function() { return null; }

// Description of the entity for whom this schedule is being generated.
SchedulePage.prototype.GetDescription = function() { return null; }

// Renders a back-button trigger element and returns the created element.
SchedulePage.prototype.RenderBackButton = function() {
  var backButtonElement = document.createElement('div');
  backButtonElement.setAttribute('material-main-button-function', 'back');

  return backButtonElement;
};

// Expands |entry| in multiple entries if it spans multiple days.
SchedulePage.prototype.ExpandEntry = function(entry) {
  return [entry];
};

// Renders the individual entry for |entry| into a list item element.
SchedulePage.prototype.RenderSingleEntry = function(entry) {
  var listContainer = document.createElement('li'),
      timeElement = document.createElement('div'),
      eventElement = document.createElement('div'),
      eventTitle = document.createElement('h2'),
      eventDescription = document.createElement('p');

  listContainer.className = 'list-item-event ' + (entry.className || '');
  if (DateUtils.isNight(entry.beginTime))
    listContainer.className += ' event-night';

  listContainer.setAttribute('event-begin', entry.beginTime);
  listContainer.setAttribute('event-end', entry.endTime);

  if (entry.url) {
    listContainer.setAttribute('handler', true);
    listContainer.setAttribute('handler-navigate', entry.url);
  }

  function DateToDisplayTime(date) {
    return date.toTimeString().match(/\d{2}:\d{2}/)[0];
  }

  timeElement.className = 'time';
  timeElement.textContent = DateUtils.format(entry.beginTime, DateUtils.FORMAT_SHORT_TIME) + ' ' +
                            DateUtils.format(entry.endTime, DateUtils.FORMAT_SHORT_TIME);

  eventElement.className = 'event';

  eventTitle.textContent = entry.name;
  eventDescription.innerHTML = entry.description;

  eventElement.appendChild(eventTitle);
  eventElement.appendChild(eventDescription);

  listContainer.appendChild(timeElement);
  listContainer.appendChild(eventElement);

  return listContainer;
};

SchedulePage.prototype.RenderNoEntriesBar = function(noDataMessage) {
  var container = document.createElement('div'),
      header = document.createElement('h2'),
      list = document.createElement('ol'),
      listItem = document.createElement('li');

  container.className = 'overview-day';
  list.className = 'material-list material-list-border';
  listItem.className = 'list-item-no-content';

  header.textContent = 'Schedule';
  listItem.innerHTML = '<i>' + noDataMessage + '</i>';

  list.appendChild(listItem);

  container.appendChild(header);
  container.appendChild(list);

  return container;
};

// Renders |entries| to a new document fragment and returns it. The entries
// must be instances of ScheduleEntry objects.
SchedulePage.prototype.RenderEntries = function(entries, noDataMessage) {
  var entriesPerDay = {},
      self = this;

  if (!entries.length)
    return this.RenderNoEntriesBar(noDataMessage);

  entries.sort(function(lhs, rhs) {
    if (lhs.beginTime == rhs.beginTime)
      return 0;

    return lhs.beginTime > rhs.beginTime ? 1 : -1;
  });

  entries.forEach(function(possiblySpanningEntry) {
    self.ExpandEntry(possiblySpanningEntry).forEach(function(entry) {
      var dateString = new Date(entry.beginTime).toDateString();
      if (!(dateString in entriesPerDay))
        entriesPerDay[dateString] = [];

      entriesPerDay[dateString].push(entry);
    });
  });

  var todayTime = Date.parse(new Date(DateUtils.getTime()).toDateString()),
      days = Object.keys(entriesPerDay).map(Date.parse);

  days.sort(function(lhs, rhs) {
    if (lhs >= todayTime && rhs < todayTime)
      return -1;

    if (lhs < todayTime && rhs >= todayTime)
      return 1;

    return lhs > rhs ? 1 : -1;
  });

  var container = document.createDocumentFragment();
  days.forEach(function(integralDay) {
    var dayContainer = document.createElement('div'),
        dayHeader = document.createElement('h2'),
        dayList = document.createElement('ol'),
        day = new Date(integralDay).toDateString();

    dayContainer.className = 'overview-day';
    if (integralDay < todayTime)
      dayContainer.className = 'past';

    dayHeader.textContent =
        SchedulePage.DAYS[new Date(entriesPerDay[day][0].beginTime).getDay()];

    dayList.className = 'material-list material-list-border';
    entriesPerDay[day].forEach(function(entry) {
      dayList.appendChild(self.RenderSingleEntry(entry));
    });

    dayContainer.appendChild(dayHeader);
    dayContainer.appendChild(dayList);

    container.appendChild(dayContainer);
  });

  return container;
};

// Resolves |variable| against our local state. This allows us to use content
// handlers specific to this page without needing to pollute global state.
SchedulePage.prototype.ResolveVariable = function(variable) {
  switch(variable) {
    case 'image':
      return this.GetImage();
    case 'name':
      return this.GetName();
    case 'description':
      return this.GetDescription();
  }

  // Otherwise we fall through to the parent class' resolve method.
  return Page.prototype.ResolveVariable.call(this);
};


// Returns the name of a schedule page. The name will be used if the page
// exists, or a more generic Oops will be returned otherwise.
SchedulePage.prototype.GetTitle = function() {
  return this.GetName() || 'Oops!';
};

// Returns the template of the page as it should be rendered. This will be
// determined based on the name of the entity being displayed.
SchedulePage.prototype.GetTemplate = function() {
  if (!this.GetName())
    return 'schedule-error-page';

  return 'schedule-page';
};
