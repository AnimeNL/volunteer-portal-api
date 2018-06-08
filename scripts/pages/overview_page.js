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
OverviewPage.prototype.FormatOtherStewards = function(shift, plurals, suffix, session_time) {
  var volunteers = [];

  shift.event.shifts.forEach(function(shift) {
    if (shift.beginTime > session_time ||
        shift.endTime <= session_time)
      return;

    if (shift.volunteer.name == this.application_.GetUser().name)
      return;

    volunteers.push(shift.volunteer);

  }.bind(this));

  if (!volunteers.length)
    return '';

  function GetFirstName(name) {
    return name.split(' ')[0];
  }

  var prefix = '';
  if (volunteers.length >= 8) prefix = 'Plenty of other volunteers ' + plurals[1];
  else if (volunteers.length == 7) prefix = 'Seven other volunteers ' + plurals[1];
  else if (volunteers.length == 6) prefix = 'Six other volunteers ' + plurals[1];
  else if (volunteers.length == 5) prefix = 'Five other volunteers ' + plurals[1];
  else if (volunteers.length == 1) prefix = GetFirstName(volunteers[0].name) + ' ' + plurals[0];
  else {
    volunteers.sort(function(lhs, rhs) {
      return lhs.name.localeCompare(rhs.name);
    });

    for (var index = 0; index < volunteers.length - 2; ++index)
      prefix += GetFirstName(volunteers[index].name) + ', ';

    prefix += GetFirstName(volunteers[volunteers.length-2].name) + ' and ';
    prefix += GetFirstName(volunteers[volunteers.length-1].name) + ' ' + plurals[1];
  }

  return ' ' + prefix + ' ' + suffix;
};

// Compiles the text to display in the "current shift" box.
OverviewPage.prototype.CompileTextForCurrentEvent = function(shift) {
  var currentTime = DateUtils.getTime(),
      remainingTime = shift.endTime - currentTime,
      message = '';

  message  = 'You have ' + this.FormatTime(remainingTime) + ' remaining on this shift.';
  message += this.FormatOtherStewards(shift, ['is', 'are'], 'with you right now.', currentTime);

  return message;
};

// Compiles the text to display in the "next shift" box.
OverviewPage.prototype.CompileTextForFutureShift = function(shift) {
  var currentTime = DateUtils.getTime(),
      remainingTime = shift.beginTime - currentTime,
      message = '';

  message  = 'This shift will begin in ' + this.FormatTime(remainingTime) + '.';
  message += this.FormatOtherStewards(shift, ['', ''], 'will be joining you.', shift.beginTime);

  return message;
};

// Renders a highlight box for |shift|. The |settings| dictionary indicates some
// additional options to consider when rendering the box.
OverviewPage.prototype.RenderShift = function(shift, settings) {
  var container = document.createElement('div'),
      header = document.createElement('h3'),
      text = document.createElement('p');

  var event = shift.event;
  var session = event.getSessionForTime(DateUtils.getTime());

  container.className = 'material-card material-card-root card-shift ' + settings.className;
  container.addEventListener('click', function() {
    this.application_.Navigate('/events/' + event.slug + '/');

  }.bind(this));

  header.textContent = settings.titlePrefix + session.name;
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
  var currentShiftElement = content.querySelector('#current_shift'),
      upcomingShiftElement = content.querySelector('#upcoming_shift'),
      shiftCountElement = content.querySelector('#shift_count'),
      volunteer = this.schedule_.findVolunteer(application.GetUser().name);

  // Update the total number of shifts in the introductionary text.
  if (shiftCountElement)
    shiftCountElement.textContent = volunteer.shifts.length;

  // Display clear banners for the current and/or upcoming shift of the
  // logged in steward. These help us quickly navigate where to go next.
  var currentTime = DateUtils.getTime(),
      currentShift = null,
      upcomingShift = null;

  volunteer.shifts.forEach(function(shift) {
    if (shift.endTime < currentTime)
      return;

    if (shift.beginTime < currentTime) {
      currentShift = shift;
      return;
    }

    if (!upcomingShift || upcomingShift.beginTime > shift.beginTime)
      upcomingShift = shift;
  });

  if (!currentShiftElement || !upcomingShiftElement)
    return;

  while (currentShiftElement.firstChild)
    currentShiftElement.removeChild(currentShiftElement.firstChild);

  if (currentShiftElement && currentShift) {
    currentShiftElement.appendChild(
        this.RenderShift(currentShift, { className: 'card-shift-current',
                                         titlePrefix: 'Current shift: ',
                                         future: false }));
  }

  while (upcomingShiftElement.firstChild)
    upcomingShiftElement.removeChild(upcomingShiftElement.firstChild);

  if (upcomingShiftElement && upcomingShift) {
    upcomingShiftElement.appendChild(
        this.RenderShift(upcomingShift, { className: 'card-shift-upcoming',
                                          titlePrefix: 'Next shift: ',
                                          future: true }));
  }

  // 2018 hack - happy birthday display
  var happyBirthday = content.querySelector('#happy-birthday');
  if (happyBirthday) {
    var normalizedCurrentTime = DateUtils.toTargetTimezone(currentTime);
    if (DateUtils.formatDate(normalizedCurrentTime) === '2018-06-14')
      happyBirthday.style.display = 'block';
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
