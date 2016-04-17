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
  this.next_update_ = GetCurrentDate().getTime() + 86400100;
};

// Builds the DOM row for displaying |steward| in the list of stewards.
StewardsPage.prototype.BuildStewardRow = function(steward) {
  var row = document.createElement('li'),
      image = document.createElement('img'),
      container = document.createElement('div'),
      name = document.createElement('h2'),
      title = document.createElement('p'),
      titleHighlight = document.createElement('span');

  row.className = 'list-item-steward material-ripple light';
  image.src = steward.photo;
  name.textContent = steward.name;
  titleHighlight.textContent = steward.getStatusLine();

  title.appendChild(titleHighlight);

  var activeShift = steward.GetCurrentShift(),
      nextShift = steward.GetNextShift();

  if (activeShift !== null) {
    var activeText = ' - ' + activeShift.event.GetName() + ' until ';
    activeText += ('0' + activeShift.end.getHours()).substr(-2) + ':';
    activeText += ('0' + activeShift.end.getMinutes()).substr(-2);

    title.appendChild(document.createTextNode(activeText));
    row.className += ' active';
  }

  // Store the next update time for the stewards overview page.
  if (activeShift)
    this.next_update_ = Math.min(this.next_update_, activeShift.end.getTime());
  else if (nextShift)
    this.next_update_ = Math.min(this.next_update_, nextShift.begin.getTime());

  var telephoneIcon = null;
  if (steward.telephone !== null) {
    telephoneIcon = document.createElement('span');
    telephoneIcon.className = 'telephone';
    telephoneIcon.textContent = '\uE0B0';
  }

  container.appendChild(name);
  container.appendChild(title);

  row.appendChild(image);
  if (telephoneIcon)
    row.appendChild(telephoneIcon);

  row.appendChild(container);

  row.setAttribute('handler', true);
  row.setAttribute('handler-navigate', '/stewards/' + steward.slug + '/');

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

  var stewardList = document.createDocumentFragment(),
      stewards = this.schedule_.GetStewards(),
      self = this;

  stewards.sort(function(lhs, rhs) {
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

  if (GetCurrentDate().getTime() < this.next_update_)
    return;  // no update is neccesary.

  this.ResetNextUpdate();
  this.OnRender(this.application_, null, document.querySelector('#content'));
  this.application_.InstallHandlers(document.querySelector('#content'));
};

StewardsPage.prototype.GetTitle = function() { return 'Stewards'; };

StewardsPage.prototype.GetTemplate = function() { return 'stewards-page'; };
