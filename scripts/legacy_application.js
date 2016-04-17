// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var LegacyApplication = function(config, container, callback) {
  this.config_ = config;
  this.container_ = container;

  this.last_schedule_update_ = Date.now();

  // DOM elements outside of |container| which can be modified by the app.
  this.themeColorElements_ = [
    document.querySelector('meta[name=theme-color]'),
    document.querySelector('meta[name=apple-mobile-web-app-status-bar-style]')
  ];

  this.titleElement_ = document.querySelector('title');

  // Cache the name of the layout used to present the current page to stop us
  // from cycling the entire DOM on every navigation.
  this.layoutTemplate_ = null;

  // The current page that is being displayed for the user.
  this.page_ = null;
  this.path_ = null;

  // Handlers get run whenever an element is about to be added to the DOM. They
  // have the ability to attach JavaScript functionality to content.
  this.handlers_ = [
    new ContentHandler(this),
    new EventUpdateHandler(),
    new LinkHandler(this),
    new MenuHandler(),
    new RippleHandler()
  ];

  // Listen to visibilitystate change events in the browser.
  ['visibilitychange', 'msvisibilitychange'].forEach(function(eventName) {
    document.addEventListener(eventName,
        LegacyApplication.prototype.OnVisibilityStateChange.bind(this));

  }.bind(this));

  // Listen to state change events triggered by the browser.
  window.addEventListener('popstate',
      LegacyApplication.prototype.OnBrowserNavigate.bind(this));

  // The schedule contains the stewards, events, times and everything else
  // required in order to know who goes where and when.
  this.schedule_ = window.application.ready.then(function(application) {
    this.user_ = application.user;
  
    // Trigger the first periodic update, which will automatically trigger further
    // updates depending visibility of the screen.
    this.OnPeriodicUpdate();

    // Execute the rest of the initialization now that this has completed.
    callback();

    return application.convention;

  }.bind(this));
};

// -----------------------------------------------------------------------------
// Constants.

// Number of milliseconds between periodic application updates.
LegacyApplication.PERIODIC_UPDATE_RATE_MS = 5000;

// Number of milliseconds between schedule update checks (15 minutes).
LegacyApplication.SCHEDULE_UPDATE_RATE_MS = 15 * 60 * 1000;

// -----------------------------------------------------------------------------
// Simple getters.

// Returns an immutable object containing the event's configuration.
LegacyApplication.prototype.GetConfig = function() {
  return this.config_;
};

// Returns the Page object that's currently being displayed to the user.
LegacyApplication.prototype.GetPage = function() {
  return this.page_;
};

// Returns the schedule of the event. Requires the event to be loaded.
LegacyApplication.prototype.GetSchedule = function() {
  return this.schedule_;
};

// Returns the User object belonging to the current application.
LegacyApplication.prototype.GetUser = function() {
  return this.user_;
};

// -----------------------------------------------------------------------------
// Navigation-related methods.

// Routes the request contained in |path| to the appropriate page. The user must
// be logged in to use this application, so unless that's the case the login
// page will be forced upon them. Returns a promise that resolves when the
// navigation has completed, and the content has been included in the DOM tree.
LegacyApplication.prototype.Navigate = function(path, ignoreNavigation) {
  if (!this.user_.isIdentified())
    return this.NavigateToPage(LoginPage);

  path = path || this.path_;

  if (this.path_ == path)
    ignoreNavigation = true;

  this.path_ = path;

  var targetPage = OverviewPage;
  var parameters = path.replace(/(^\/|\/$)/g, '')
                       .split('/');

  switch (parameters[0]) {
    case 'events':
      targetPage = EventPage;
      break;

    case 'floors':
      targetPage = FloorPage;
      if (parameters.length > 2)
        targetPage = LocationPage;
      break;

    case 'stewards':
      targetPage = StewardsPage;
      if (parameters.length > 1)
        targetPage = StewardOverviewPage;
      break;
  }

  return this.NavigateToPage(targetPage, parameters).then(function(scroll) {
    // Update the current history entry with the scroll offset.
    history.replaceState({ path: document.location.pathname,
                           scroll: scroll }, '' /* title */,
                         document.location.pathname);

    // If the navigation shouldn't be ignored, push the new page in the
    // browser's history service, enabling the back/forward buttons.
    if (!ignoreNavigation)
      history.pushState({ path: path }, '' /* title */, path);
  });
};

LegacyApplication.prototype.NavigateToPage = function(classObject, parameters) {
  var page = new classObject(this, parameters),
      self = this;

  return page.PrepareRender(this.page_).then(function() {
    var template = page.GetTemplate(),
        content = null,
        scroll = null;

    self.page_ = null;

    var layoutTemplate = page.GetLayoutTemplate();
    if (layoutTemplate != self.GetLayoutTemplate())
      self.SetLayoutTemplate(layoutTemplate);

    if (template != null)
      content = TemplateFactory.Get(template);

    self.page_ = page;

    page.OnRender(self, self.container_, content);

    if (content != null)
      scroll = self.SetContent(content);

    var title = page.GetTitle();
    if (title != null)
      self.SetTitle(title);

    self.SetThemeColor(page.GetThemeColor());
    return scroll;
  });
};

LegacyApplication.prototype.OnDisplayMySchedule = function(event) {
  var self = this;

  // TODO: This should handle cases where the current user is a view-only one.

  this.schedule_.then(schedule => {
    var volunteer = schedule.findVolunteer(this.user_.name);

    if (!volunteer)
      self.Navigate('/');
    else
      self.Navigate('/stewards/' + volunteer.slug + '/me/');
  });
};

LegacyApplication.prototype.OnRequestNavigate = function(event) {
  this.Navigate(event.pageName);
};

LegacyApplication.prototype.OnBrowserNavigate = function(event) {
  if (!event.state || !('path' in event.state))
    return;

  this.Navigate(event.state.path, true /* ignoreNavigation */).then(function() {
    if ('scroll' in event.state && event.state.scroll !== null)
      this.SetContentScroll(event.state.scroll);

  }.bind(this));
};

// -----------------------------------------------------------------------------
// Handler-related methods.

LegacyApplication.prototype.InstallHandlers = function(element) {
  this.handlers_.forEach(function(handler) {
    handler.OnRender(element);
  });
};

// -----------------------------------------------------------------------------
// Layout and content methods.

LegacyApplication.prototype.GetLayoutTemplate = function() {
  return this.layoutTemplate_;
};

LegacyApplication.prototype.SetLayoutTemplate = function(templateName) {
  this.layoutTemplate_ = templateName;

  var layout = TemplateFactory.Get(templateName);

  this.InstallHandlers(layout);

  // Fast-path for the first page load, where the content will be added
  // immediately rather than animate in from the right-hand side.
  if (!this.container_.firstChild) {
    this.container_.appendChild(layout);
    return;
  }

  // Slow-path for other transitions, which will occur when the user signs in or
  // out. These transition the page in from the right-hand side.
  var previousContainer = this.container_,
      createdContainer = document.createElement('div');

  var transitionDirection = templateName == 'login' ?
      'before-transition-reverse' :
      'before-transition';

  createdContainer.className = 'container inserted opening '
      + transitionDirection;

  createdContainer.appendChild(layout);

  var eventListener = function(event) {
    // Remove the event listener since it's only needed as a one-shot.
    createdContainer.removeEventListener('transitionend', eventListener);

    previousContainer.parentElement.removeChild(previousContainer);
    createdContainer.classList.remove('opening');
  };

  createdContainer.addEventListener('transitionend', eventListener);

  this.container_.parentElement.insertBefore(createdContainer, this.container_);
  this.container_ = createdContainer;

  // Remove the 'opening' class in the next animation frame, which will start
  // the slide-in transition automagically.
  requestAnimationFrame(function() {
    createdContainer.classList.remove(transitionDirection);
  });
};

LegacyApplication.prototype.SetContent = function(contentNode) {
  this.InstallHandlers(contentNode);

  // TODO(peter): Perhaps have an animation here if I'm bored. Otherwise let's
  // not bother, the event is roughly a month away at this point.

  var container = document.getElementById('content');
  if (!container)
    return null;

  var currentScroll = container.scrollTop;

  // Clear out the existing content.
  while (container.firstChild)
    container.removeChild(container.firstChild);
  
  // Add |contentNode| as the only contents of the content container.
  container.appendChild(contentNode);

  // Reset the scroll position of the content container.
  container.scrollTop = 0;

  return currentScroll;
};

LegacyApplication.prototype.SetContentScroll = function(scroll) {
  var container = document.getElementById('content');
  if (container)
    container.scrollTop = scroll;
};

LegacyApplication.prototype.SetThemeColor = function(color) {
  this.themeColorElements_.forEach(function(element) {
    if (element)
      element.content = color;
  });
};

LegacyApplication.prototype.SetTitle = function(title) {
  var suffix = this.config_['title'];

  // First update the header element if the layout includes one.
  var headerTitle = document.getElementById('header-title');
  if (headerTitle)
    headerTitle.textContent = title;

  // Then update the page's <title> element.
  if (!this.titleElement_)
    return;
  
  if (title && title.length && title != this.config_.title)
    this.titleElement_.textContent = title + ' | ' + suffix;
  else
    this.titleElement_.textContent = suffix;
};

// -----------------------------------------------------------------------------
// Click event handlers.

LegacyApplication.prototype.OnToggleHiddenEvents = function(event) {
  this.user_.setOption('hidden_events', !this.user_.getOption('hidden_events', false));
  document.location.reload();
};

LegacyApplication.prototype.OnRefresh = function(event) {
  document.location.href = '/';
};

LegacyApplication.prototype.OnSignOut = function(event) {
  this.user_.signOut().then(function() {
    this.Navigate('/');

  }.bind(this));
};

// -----------------------------------------------------------------------------
// LegacyApplication state invalidation.

LegacyApplication.prototype.IsDocumentHidden = function() {
  if (typeof document.hidden != 'undefined')
    return document.hidden;

  if (typeof document.msHidden != 'undefined')
    return document.msHidden;

  return false;
};

LegacyApplication.prototype.OnVisibilityStateChange = function() {
  this.OnPeriodicUpdate();
};

LegacyApplication.prototype.OnPeriodicUpdate = function() {
  var container = this.container_;
  this.handlers_.forEach(function(handler) {
    handler.OnPeriodicUpdate(container);
  });

  var schedule_update_diff = Date.now() - this.last_schedule_update_;
  if (schedule_update_diff >= LegacyApplication.SCHEDULE_UPDATE_RATE_MS) {
    this.schedule_.then(function(schedule) {
      schedule.CheckForScheduleUpdate();
    });

    this.last_schedule_update_ = Date.now();
  }

  if (this.page_)
    this.page_.OnPeriodicUpdate();

  // Schedule the next periodic update to happen in 30 seconds, but only if the
  // page is currently visible. (Updates will be postponed otherwise.)
  if (this.IsDocumentHidden())
    return;

  setTimeout(LegacyApplication.prototype.OnPeriodicUpdate.bind(this),
             LegacyApplication.PERIODIC_UPDATE_RATE_MS);
};

// -----------------------------------------------------------------------------

function GetCurrentDate() {
  return new Date(window.application.getTime());
}

// -----------------------------------------------------------------------------

// Activate the application when the DOM has finished loading.
addEventListener('DOMContentLoaded', function() {
  var container = document.querySelector('.container'),
      config = new Config(window.config);

  window.legacyApplication = new LegacyApplication(config, container, function() {
    window.legacyApplication.Navigate(location.pathname, true /* ignoreNavigation */)
        .then(function() {
      requestAnimationFrame(function() {
        container.classList.add('visible');
      });
    });
  });

}, false);
