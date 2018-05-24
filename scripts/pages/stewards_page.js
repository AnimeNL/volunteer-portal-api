// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var StewardsPage = function(application, parameters) {
  Page.call(this, application);

  this.parameters_ = parameters;
  this.volunteer_ = null;
  this.schedule_ = null;

  this.ResetNextUpdate();
};

StewardsPage.prototype = Object.create(Page.prototype);

StewardsPage.prototype.ResetNextUpdate = function() {
  this.next_update_ = DateUtils.getTime() + 86400100;
};

// Builds the DOM row for displaying |volunteer| in the list of stewards.
StewardsPage.prototype.BuildStewardRow = function(volunteer) {
  var row = document.createElement('li'),
      image = document.createElement('img'),
      container = document.createElement('div'),
      name = document.createElement('h2'),
      title = document.createElement('p'),
      titleHighlight = document.createElement('span');

  row.className = 'list-item-steward material-ripple light';
  image.src = volunteer.photo;
  name.textContent = volunteer.name;
  titleHighlight.textContent = volunteer.title;

  title.appendChild(titleHighlight);

  var currentTime = DateUtils.getTime();
  var shift = volunteer.getCurrentOrUpcomingShift(currentTime);

  if (!volunteer.cachedIsAvailable)
    row.className += ' unavailable';

  if (shift && shift.current) {
    var session = shift.event.getSessionForTime(currentTime);

    var activeText = ' - ' + session.name + ' until ';
    activeText += DateUtils.format(shift.endTime, DateUtils.FORMAT_SHORT_TIME);

    title.appendChild(document.createTextNode(activeText));

    row.className += ' active';
    if (session.name == 'Backup')
      row.className += ' active-backup';
  }

  // Store the next update time for the stewards overview page.
  if (shift && shift.current)
    this.next_update_ = Math.min(this.next_update_, shift.endTime);
  else if (shift && !shift.current)
    this.next_update_ = Math.min(this.next_update_, shift.beginTime);

  var badgeIcon = null;
  if (volunteer.isSenior()) {
    badgeIcon = document.createElement('span');
    badgeIcon.className = 'senior-badge';
    badgeIcon.textContent = '\uE8D0';
  }

  container.appendChild(name);
  container.appendChild(title);

  row.appendChild(image);
  if (badgeIcon)
    row.appendChild(badgeIcon);

  row.appendChild(container);

  row.setAttribute('handler', true);
  row.setAttribute('handler-navigate', '/volunteers/' + volunteer.slug + '/');

  return row;
};

// Ensures that the schedule is available prior to rendering this page.
StewardsPage.prototype.PrepareRender = function() {
  return this.application_.GetSchedule().then(function(schedule) {
    this.schedule_ = schedule;
    this.volunteer_ = schedule.findVolunteer(this.application_.GetUser().name);

  }.bind(this));
};

// Renders the list of available groups into |list|.
StewardsPage.prototype.RenderGroupSwitcher = function(list, groups, currentGroup) {
  // Empty the |list| of any existing groups.
  while (list.firstChild)
    list.removeChild(list.firstChild);

  // First build an array containing the groups we'd like to display, in order.
  // The volunteer's own group comes first, then any other group in alphabetical
  // order. (I guess this makes it most predictable.)

  var volunteerGroup = this.volunteer_.group;
  var volunteerGroups = [];

  for (var group in groups) {
    if (group == volunteerGroup)
      continue;

    volunteerGroups.push(group);
  }

  volunteerGroups.sort();
  volunteerGroups.unshift(volunteerGroup);

  // If there is just one entry in |volunteerGroups|, there is no point in
  // displaying a tab switcher.
  if (volunteerGroups.length <= 1) {
    list.style.display = 'none';
    return;
  }

  list.classList.add('tab-count-' + volunteerGroups.length);

  // Add rows for each of the |volunteerGroups| to the |list|.
  volunteerGroups.forEach(function(group) {
    var tab = document.createElement('li');
    if (currentGroup == group)
      tab.classList.add('selected');

    tab.setAttribute('handler', true);
    tab.setAttribute('handler-navigate', '/volunteers/g:' + group.toLowerCase() + '/');
    tab.textContent = group;

    list.appendChild(tab);
  });
};

// Builds the Steward Overview page, listing all the stewards of this event
// with a photo (when available), their name and whether they're on a shift
// at this very moment.
StewardsPage.prototype.OnRender = function(application, container, content) {
  var groupSwitcher = content.querySelector('#volunteer-type-tabs'),
      listContainer = content.querySelector('#steward-list');

  if (!groupSwitcher || !listContainer)
    return;

  var currentTime = DateUtils.getTime();

  var volunteerList = document.createDocumentFragment(),
      volunteerGroups = {},
      volunteers = this.schedule_.volunteers.slice() /* make a copy */,
      self = this;

  // Calling isAvailable() within the sorting function would be too expensive.
  // Also gather a set of volunteer groups to enable a tabbed display.
  volunteers.forEach(function(volunteer) {
    volunteer.cachedIsAvailable = volunteer.isAvailable(currentTime);
    volunteerGroups[volunteer.group] = true;
  });

  var displayGroup = null;

  // First attempt to filter by request parameter if one has been made available.
  if (this.parameters_.length > 1) {
    var filterGroup = this.parameters_[1].substr(2);
    for (var volunteerGroup in volunteerGroups) {
      if (volunteerGroup.toLowerCase() != filterGroup)
        continue;

      displayGroup = volunteerGroup;
      break;
    }
  }

  // Otherwise fall back to the local volunteer's own group.
  displayGroup = displayGroup || this.volunteer_.group;

  // Sort the volunteers based on availability, then alphabetically by full name.
  volunteers.sort(function(lhs, rhs) {
    if (lhs.cachedIsAvailable && !rhs.cachedIsAvailable)
      return -1;
    if (!lhs.cachedIsAvailable && rhs.cachedIsAvailable)
      return 1;

    return lhs.name.localeCompare(rhs.name);
  });

  volunteers.forEach(function(volunteer) {
    if (displayGroup && volunteer.group != displayGroup)
      return;

    volunteerList.appendChild(self.BuildStewardRow(volunteer));
  });

  this.RenderGroupSwitcher(groupSwitcher, volunteerGroups, displayGroup);

  while (listContainer.firstChild)
    listContainer.removeChild(listContainer.firstChild);

  listContainer.appendChild(volunteerList);
};

// The steward overview page should refresh itself when the shifts change.
Page.prototype.OnPeriodicUpdate = function() {
  if (this.next_update_ == null)
    return;  // something must be wrong.

  if (DateUtils.getTime() < this.next_update_)
    return;  // no update is neccesary.

  this.ResetNextUpdate();
  this.OnRender(this.application_, null, document.querySelector('#content'));
  this.application_.InstallHandlers(document.querySelector('#content'));
};

StewardsPage.prototype.GetTitle = function() { return 'Volunteers'; };

StewardsPage.prototype.GetTemplate = function() { return 'stewards-page'; };
