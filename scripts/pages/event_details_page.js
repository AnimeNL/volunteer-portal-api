// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var EventDetailsPage = function(application, parameters) {
  Page.call(this, application);

  this.parameters_ = parameters;

  this.event_ = null;
  this.session_ = null;
  this.schedule_ = null;
};

EventDetailsPage.prototype = Object.create(Page.prototype);

EventDetailsPage.prototype.PrepareRender = function() {
  var self = this;

  return this.application_.GetSchedule().then(function(schedule) {
    for (var i = 0; i < schedule.events.length; ++i) {
      if (schedule.events[i].slug != self.parameters_[1])
        continue;

      self.event_ = schedule.events[i];
      self.session_ = schedule.events[i].sessions[0];
      self.schedule_ = schedule;
      return;
    }
  });
};

EventDetailsPage.prototype.OnRender = function(_, container, content) {
  var contentElement = content.querySelector('#event-details-content');
  var contentCode = application.content.get(this.event_.id);

  if (!contentElement || !content)
    return;

  var contentFragment = document.createRange().createContextualFragment(contentCode),
      contentBox = null;

  while (contentFragment.firstChild) {
    var element = contentFragment.firstChild;

    // Create a new content box for the initial content, as well as for the headers. This way we can
    // enable the content writers to use main headers for new boxes.
    if (!contentBox || element.tagName == 'H1') {
      var box = document.createElement('div');
      box.className = 'card card-markdown';

      if (contentBox)
        contentElement.appendChild(contentBox);

      contentBox = box;
    }

    // Moves the |element| to the created content box.
    contentBox.appendChild(element);
  }

  if (contentBox)
    contentElement.appendChild(contentBox);
};

EventDetailsPage.prototype.ResolveVariable = function(variable) {
  switch(variable) {
    case 'name':
      return this.GetTitle();
  }

  // Otherwise we fall through to the parent class' resolve method.
  return Page.prototype.ResolveVariable.call(this);
};

EventDetailsPage.prototype.GetTitle = function() {
  return this.session_ ? this.session_.name : 'Oops!';
};

EventDetailsPage.prototype.GetTemplate = function() {
  return this.session_ ? 'event-details-page' : 'event-error-page';
};
