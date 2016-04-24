// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

// Supports updating the state of scheduled events which have a set start and
// end time. Such events will always have one of the following class names
// assigned to them: [past, active, future].
var EventUpdateHandler = function() {};

// Updates |element|s class name depending on whether the event is currently
// active. The element will only be touched if the state changes.
EventUpdateHandler.prototype.UpdateElement = function(element, begin, end, time) {
  var classNames = {
    past: element.getAttribute('event-class-past') || 'past',
    active: element.getAttribute('event-class-active') || 'active',
    future: element.getAttribute('event-class-future') || 'future'
  };

  var className = classNames.future;
  if (end < time)
    className = classNames.past;
  else if (begin <= time && end > time)
    className = classNames.active;

  if (element.classList.contains(className))
    return;

  ['past', 'active', 'future'].forEach(function(name) {
    element.classList.remove(classNames[name]);
  });

  element.classList.add(className);
};

// Called when the events in |rootElement| should have their active and past
// status updated. All elements with begin and end times will be considered.
EventUpdateHandler.prototype.UpdateTree = function(rootElement) {
  var elements = rootElement.querySelectorAll('[event-begin][event-end]'),
      currentTime = DateUtils.getTime();

  for (var i = 0; i < elements.length; ++i) {
    this.UpdateElement(elements[i],
                       elements[i].getAttribute('event-begin'),
                       elements[i].getAttribute('event-end'),
                       currentTime);
  }
};

// Called when an element has been rendered due to a navigation.
EventUpdateHandler.prototype.OnRender =
    EventUpdateHandler.prototype.UpdateTree;

// Called when the entire application should update its state.
EventUpdateHandler.prototype.OnPeriodicUpdate =
    EventUpdateHandler.prototype.UpdateTree;
