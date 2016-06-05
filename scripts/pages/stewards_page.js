// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var StewardsPage = function(application) {
  Page.call(this, application);

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
  titleHighlight.textContent = volunteer.getStatusLine();

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
  row.setAttribute('handler-navigate', '/stewards/' + volunteer.slug + '/');

  return row;
};

// Ensures that the schedule is available prior to rendering this page.
StewardsPage.prototype.PrepareRender = function() {
  return this.application_.GetSchedule().then(function(schedule) {
    this.schedule_ = schedule;

  }.bind(this));
};

// Builds the Steward Overview page, listing all the stewards of this event
// with a photo (when available), their name, title and whether they're on
// a shift at this very moment.
StewardsPage.prototype.OnRender = function(application, container, content) {
  var listContainer = content.querySelector('#steward-list');
  if (!listContainer)
    return;

  var currentTime = DateUtils.getTime();
  var stewardList = document.createDocumentFragment(),
      stewards = this.schedule_.volunteers.slice() /* make a copy */,
      self = this;

  // Calling isAvailable() within the sorting function would be too expensive.
  stewards.forEach(function(steward) {
    steward.cachedIsAvailable = steward.isAvailable(currentTime);
  });

  stewards.sort(function(lhs, rhs) {
    if (lhs.cachedIsAvailable && !rhs.cachedIsAvailable)
      return -1;
    if (!lhs.cachedIsAvailable && rhs.cachedIsAvailable)
      return 1;

    return lhs.name.localeCompare(rhs.name);
  });

  stewards.forEach(function(steward) {
    stewardList.appendChild(self.BuildStewardRow(steward));
  });

  while (listContainer.firstChild)
    listContainer.removeChild(listContainer.firstChild);

  listContainer.appendChild(stewardList);
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

StewardsPage.prototype.GetTitle = function() { return 'Stewards'; };

StewardsPage.prototype.GetTemplate = function() { return 'stewards-page'; };
