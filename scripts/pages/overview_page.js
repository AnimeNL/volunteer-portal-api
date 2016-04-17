// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var OverviewPage = function(application) {
  SchedulePage.call(this, application);
};

OverviewPage.prototype = Object.create(SchedulePage.prototype);

// Formats the time to display for a current or upcoming shift box.
OverviewPage.prototype.FormatTime = function(time) {
  time = time / 1000;  // bring the time back to seconds

  if (time <= 60)
    return Math.floor(time) + ' seconds';

  var minutes = Math.floor(time / 60);
  if (minutes >= 2880)
    return Math.floor(minutes / 1440) + ' days';
  else if (minutes >= 120)
    return Math.floor(minutes / 60) + ' hours';
  else if (minutes > 60)
    return Math.floor(minutes / 60) + ':' + ('0' + (minutes % 60)).substr(-2) + ' hours';
  
  return minutes + ' minutes'; 
};

// Formats a list of other stewards which will be joining the current user on a
// particular shift. There's really no justification behind this complexity, it's
// just a fun element on the overview page.
OverviewPage.prototype.FormatOtherStewards =
    function(shift, plurals, suffix, session_time) {
  var shifts = shift.event.GetShifts(),
      stewards = [];

  shifts.forEach(function(shift) {
    if (shift.begin.getTime() > session_time ||
        shift.end.getTime() <= session_time)
      return;

    if (shift.steward.name == this.application_.GetUser().name)
      return;

    stewards.push(shift.steward);

  }.bind(this));

  if (!stewards.length)
    return '';

  function GetFirstName(name) {
    return name.split(' ')[0];
  }

  var prefix = '';
  if (stewards.length >= 8) prefix = 'Plenty of other stewards ' + plurals[1];
  else if (stewards.length == 7) prefix = 'Seven other stewards ' + plurals[1];
  else if (stewards.length == 6) prefix = 'Six other stewards ' + plurals[1];
  else if (stewards.length == 5) prefix = 'Five other stewards ' + plurals[1];
  else if (stewards.length == 1) prefix = GetFirstName(stewards[0].name) + plurals[0];
  else {
    stewards.sort(function(lhs, rhs) {
      return lhs.name.localeCompare(rhs.name);
    });

    for (var index = 0; index < stewards.length - 2; ++index)
      prefix += GetFirstName(stewards[index].name) + ', ';

    prefix += GetFirstName(stewards[stewards.length-2].name) + ' and ';
    prefix += GetFirstName(stewards[stewards.length-1].name) + ' ' + plurals[1];
  }

  return ' ' + prefix + ' ' + suffix;
};

// Compiles the text to display in the "current shift" box.
OverviewPage.prototype.CompileTextForCurrentEvent = function(shift) {
  var current_time = GetCurrentDate().getTime(),
      remaining_time = shift.end.getTime() - current_time,
      message = '';

  message  = 'You have ' + this.FormatTime(remaining_time) + ' remaining on this shift.';
  message += this.FormatOtherStewards(shift, ['is', 'are'], 'with you right now.', current_time);

  return message;
};

// Compiles the text to display in the "next shift" box.
OverviewPage.prototype.CompileTextForFutureShift = function(shift) {
  var current_time = GetCurrentDate().getTime(),
      remaining_time = shift.begin.getTime() - current_time,
      message = '';

  message  = 'This shift will begin in ' + this.FormatTime(remaining_time) + '.';
  message += this.FormatOtherStewards(shift, ['', ''], 'will be joining you.', shift.begin.getTime());

  return message;
};

// Renders a highlight box for |shift|. The |settings| dictionary indicates some
// additional options to consider when rendering the box.
OverviewPage.prototype.RenderShift = function(shift, settings) {
  var container = document.createElement('div'),
      header = document.createElement('h3'),
      text = document.createElement('p');

  container.className = 'material-card material-card-root card-shift ' + settings.className;
  container.addEventListener('click', function() {
    this.application_.Navigate(shift.event.GetNavigateLocation());

  }.bind(this));

  header.textContent = settings.titlePrefix + shift.event.GetName();
  if (settings.future)
    text.textContent = this.CompileTextForFutureShift(shift);
  else
    text.textContent = this.CompileTextForCurrentEvent(shift);

  container.appendChild(header);
  container.appendChild(text);

  return container;
};

// Renders the overview page. This page will be rendered every few seconds while
// the user is viewing it, so it must remain as simple as we can make it.
OverviewPage.prototype.OnRender = function(application, container, content) {
  var current_shift_element = content.querySelector('#current_shift'),
      upcoming_shift_element = content.querySelector('#upcoming_shift'),
      shift_count = content.querySelector('#shift_count'),
      steward = this.schedule_.GetSteward(application.GetUser().name);

  // Update the total number of shifts in the introductionary text.
  if (shift_count)
    shift_count.textContent = steward.GetShifts().length;

  // Display clear banners for the current and/or upcoming shift of the
  // logged in steward. These help us quickly navigate where to go next.
  var current_time = GetCurrentDate().getTime(),
      current_shift = null,
      upcoming_shift = null;

  steward.GetShifts().forEach(function(shift) {
    if (shift.end.getTime() < current_time)
      return;

    if (shift.begin.getTime() < current_time) {
      current_shift = shift;
      return;
    }

    if (upcoming_shift == null ||
        upcoming_shift.begin.getTime() > shift.begin.getTime())
      upcoming_shift = shift;
  });

  if (!current_shift_element || !upcoming_shift_element)
    return;

  while (current_shift_element.firstChild)
    current_shift_element.removeChild(current_shift_element.firstChild);

  if (current_shift_element && current_shift) {
    current_shift_element.appendChild(
        this.RenderShift(current_shift, { className: 'card-shift-current',
                                          titlePrefix: 'Current shift: ',
                                          future: false }));
  }

  while (upcoming_shift_element.firstChild)
    upcoming_shift_element.removeChild(upcoming_shift_element.firstChild);

  if (upcoming_shift_element && upcoming_shift) {
    upcoming_shift_element.appendChild(
        this.RenderShift(upcoming_shift, { className: 'card-shift-upcoming',
                                           titlePrefix: 'Next shift: ',
                                           future: true }));
  }
};

// Re-render the contents of the page every time a periodic update occurs.
// Modern browsers will not flicker, but this will make sure that the state
// of the page remains current.
OverviewPage.prototype.OnPeriodicUpdate = function() {
  this.OnRender(this.application_, null, document.querySelector('#content'));
};

OverviewPage.prototype.GetTitle = function() {
  return this.application_.GetConfig().title;
};

OverviewPage.prototype.GetTemplate = function() {
  return 'overview-page';
};
