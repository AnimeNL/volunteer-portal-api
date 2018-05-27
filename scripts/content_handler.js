// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

// The content handler provides the ability to directly use JavaScript values
// in the templates using the "content" attribute.
//
// <a content="name">
//
//   Replaces the contents of the element with the content handler for "name".
//
var ContentHandler = function(application) {
  this.application_ = application;
  this.cache_ = {};
};

ContentHandler.UNDEFINED = '[[undefined]]';

// Initializes and updates the contents of labels that will fade into their new
// value. This is a less trivial part of Material Design to implement.
ContentHandler.prototype.UpdateLabel = function(element, value) {
  // Skip updating the label altogether if the value is undefined.
  if (value == ContentHandler.UNDEFINED)
    return;

  var current_value = element.getAttribute('content-label-value');

  // Initialize the label for the first time, building up the DOM tree.
  if (current_value == null) {
    var current_value_element = document.createElement('span'),
        upcoming_value_element = document.createElement('span');

    current_value_element.className = 'current';
    current_value_element.textContent = value;

    upcoming_value_element.className = 'upcoming';
    upcoming_value_element.addEventListener('transitionend',
        ContentHandler.prototype.OnLabelTransitionEnd.bind(this, element),
        false);

    element.appendChild(current_value_element);
    element.appendChild(upcoming_value_element);

    element.setAttribute('content-label-value', value);
    return;
  }

  // If the value hasn't changed, don't bother updating the label.
  if (current_value == value)
    return;

  // Update the upcoming value to read |value|.
  element.querySelector('.upcoming').textContent = value;

  element.setAttribute('content-label-value', value);
  element.classList.add('update');
};

// Called when the transition on menu labels has finished.
ContentHandler.prototype.OnLabelTransitionEnd = function(element, event) {
  var current_value_element = element.querySelector('.current'),
      upcoming_value_element = element.querySelector('.upcoming');

  if (upcoming_value_element.textContent == '')
    return;  // the value has already been updated.

  // Swap the current and upcoming values around to prevent reverse animation.
  current_value_element.className = 'upcoming';
  current_value_element.textContent = '';

  upcoming_value_element.className = 'current';

  // And return to the stable state.
  element.classList.remove('update');
};

// Updates |element| with the value of the variable named |variable|.
ContentHandler.prototype.UpdateElement =
    function(element, attribute, label, variable) {
  var value = ContentHandler.UNDEFINED;

  if (this.cache_.hasOwnProperty(variable)) {
    value = this.cache_[variable];
  } else {
    switch (variable) {
      case 'display-events-toggle':
        value = this.application_.GetUser().getOption('hidden_events', true)
                    ? 'Hide hidden events'
                    : 'Show hidden events';
        break;
      case 'event-name':
        value = this.application_.GetConfig().title;
        break;
      case 'label-volunteers':
        var user = this.application_.GetUser();
        var volunteer = this.application_.GetConvention().findVolunteer(user.name);

        if (!volunteer || volunteer.isSenior())
          value = 'Volunteers';
        else
          value = volunteer.group;
        break;
      case 'title-group':
        var user = this.application_.GetUser();
        var volunteer = this.application_.GetConvention().findVolunteer(user.name);

        if (!volunteer) {
          value = 'Volunteer';
        } else {
          switch (volunteer.group) {
            case 'Gophers':
              value = 'Gopher';
              break;
            case 'Stewards':
              value = 'Steward';
              break;
            default:
              value = 'Volunteer';
              break;
          }
        }
        break;
      case 'notifications-toggle':
        value = this.application_.GetUser().getOption('notifications', false)
                    ? 'Disable notifications'
                    : 'Enable notifications';
        break;
      case 'time':
        value = DateUtils.format(DateUtils.getTime(), DateUtils.FORMAT_ISO_8601);
        break;
      case 'username':
        value = this.application_.GetUser().name;
        break;
      default:
        var page = this.application_.GetPage();
        if (page)
          value = page.ResolveVariable(variable);
        break;
    }
  }

  if (label)
    this.UpdateLabel(element, value);
  else if (attribute)
    element.setAttribute(attribute, value);
  else
    element.textContent = value;
};

// Updates all elements in |element| having a content attribute with the latest
// value of the set variable.
ContentHandler.prototype.UpdateTree = function(rootElement) {
  var elements = rootElement.querySelectorAll('[content]');
  for (var index = 0; index < elements.length; ++index) {
    var element = elements[index],
        variable = elements[index].getAttribute('content'),
        attribute = elements[index].getAttribute('content-attribute'),
        label = elements[index].getAttribute('content-label') != null;

    this.UpdateElement(element, attribute, label, variable);
  }
};

// Called when an element has been added to the DOM.
ContentHandler.prototype.OnRender =
    ContentHandler.prototype.UpdateTree;

// Called when a periodic update (a few times per minute) is set to happen. The
// periodic update will refresh the caches prior to updating the tree.
ContentHandler.prototype.OnPeriodicUpdate = function(rootElement) {
  var include_hidden = this.application_.GetUser().getOption('hidden_events', true),
      self = this;

  this.application_.GetSchedule().then(function(schedule) {
    var activeEvents = schedule.findUpcomingEvents({ activeOnly: true, hidden: include_hidden });

    function countSessionsForFloor(floor) {
      if (!activeEvents.hasOwnProperty(floor))
        return 0;

      var count = 0;
      activeEvents[floor].forEach(function(locationInfo) {
        count += locationInfo.sessions.length;
      });

      return count;
    }

    self.cache_ = {
      'label-oceans':     countSessionsForFloor('-1') || '',
      'label-continents': countSessionsForFloor(0) || '',
      'label-rivers':     countSessionsForFloor(1) || '',
      'label-mountains':  countSessionsForFloor(2) || '',
    };

    // Update the tree now that the caches have been reset.
    self.UpdateTree(rootElement);
  });
};

ContentHandler.prototype.CreateMenuLabel = function(events) {
  if (!events.length)
    return '';

  // TODO(peter): Display the steward count on this floor if it makes sense?
  return events.length;
};
