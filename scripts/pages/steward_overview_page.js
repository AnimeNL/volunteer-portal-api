// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var StewardOverviewPage = function(application, parameters) {
  SchedulePage.call(this, application);

  this.parameters_ = parameters;

  this.enableBackButton_ = parameters.length == 2;
  this.steward_ = null;
};

StewardOverviewPage.prototype = Object.create(SchedulePage.prototype);

// Called right before we start rendering the page. Determines the Steward that
// this overview page is about, making it available in |this.steward_|.
StewardOverviewPage.prototype.PrepareRender = function(currentPage) {
  var parentMethod = SchedulePage.prototype.PrepareRender.bind(this),
      self = this;

  return parentMethod().then(function(schedule) {
    self.steward_ = schedule.findVolunteer(self.parameters_[1], true /* isSlug */);

    // If |self.steward_| could be set then we located the Steward for whom this
    // overview page is. If it's NULL then an error page will be shown instead.
  });
};

// Renders a telephone icon which, when clicked on, will open the device's dialer
// for the number associated with the current steward.
StewardOverviewPage.prototype.RenderTelephone = function(container) {
  var element = document.createElement('div'),
      link = document.createElement('a');

  link.textContent = '\uE0B0';
  link.setAttribute('href', 'tel:' + this.steward_.telephone);

  element.className = 'steward-telephone';
  element.appendChild(link);

  container.insertBefore(element, container.firstChild);
};

// Called when the page is being rendered. We fill in information about the
// schedule of this steward here.
StewardOverviewPage.prototype.OnRender = function(application, container, content) {
  if (this.steward_ == null)
    return;  // we don't know who this is.

  if (this.enableBackButton_)
    content.insertBefore(this.RenderBackButton(), content.firstChild);

  var listContainer = content.querySelector('#schedule-contents');
  if (!listContainer)
    return;

  var infoBox = content.querySelector('#info-box');
  if (infoBox && this.steward_.telephone)
    this.RenderTelephone(infoBox);

  var entries = [];

  var currentTime = DateUtils.getTime();

  // A steward's schedule doesn't care about the timings of the event itself, but
  // rather cares about their shift and then the event's information.
  this.steward_.shifts.forEach(function(shift) {
    var session = shift.event.getSessionForTime(currentTime);
    var timespan = shift.beginTime + '-' + shift.endTime;

    entries.push({
      name: session.name,
      description: session.description,

      beginTime: shift.beginTime,
      endTime: shift.endTime,

      location: session.location.name,
      url: '/events/' + shift.event.slug + '/' + timespan + '/',

      className: session.isHidden() ? 'hidden' : ''
    });
  });

  listContainer.appendChild(
      this.RenderEntries(entries,
                         'No shifts have been scheduled for this steward.'));
};

//
StewardOverviewPage.prototype.GetImage = function() {
  return this.steward_ ? this.steward_.photo : null;
};

StewardOverviewPage.prototype.GetName = function() {
  return this.steward_ ? this.steward_.name : null;
};

StewardOverviewPage.prototype.GetDescription = function() {
  if (!this.steward_)
    return null;

  return this.steward_.title;
};
