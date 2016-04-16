// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

// The link handler provides for the ability to bind JavaScript events to the
// DOM automagically, through the <a handler> attribute. It will have the
// following behaviour:
//
// <a handler> (boolean attribute)
//
//   The OnClick method on the current page will be invoked with the click
//   event as the only attribute. event.target may be used to identify which
//   element was clicked on by the user.
//
// <a handler="OnSignOut">
//
//   The OnSignOut method will be invoked on the current page. The click
//   event will be included as the only argument.
//
// <a handler="OnSignOut" handler-application>
//
//   The OnSignOut method will be invoked directly on the application rather
//   than on the page visible right now. This is useful for links that
//   apply to all pages rather than just the current one.
//
// <a handler handler-navigate="/path/to/page">
//
//   The page will automatically navigate to the value of the |handler-navigate|
//   attribute.
//
var LinkHandler = function(application) {
  this.application_ = application;
};

// Called when |renderedElement| has been added to the DOM. This is the right
// time to attach the necessary event listeners to its children.
LinkHandler.prototype.OnRender = function(renderedElement) {
  var elements = renderedElement.querySelectorAll('[handler]');
  for (var index = 0; index < elements.length; ++index) {
    var element = elements[index],
        handler = element.getAttribute('handler') || 'OnClick',
        handlerApplication =
            element.getAttribute('handler-application') != null,
        handlerNavigate =
            element.getAttribute('handler-navigate');

    element.addEventListener('click',
        LinkHandler.prototype.OnClick.bind(this,
                                           handler,
                                           handlerApplication,
                                           handlerNavigate), false);
  }
};

// Called when a link handler has been clicked on. This method is responsible
// for (safely) routing the event to the appropriate handler.
LinkHandler.prototype.OnClick =
    function(handler, handlerApplication, handlerNavigate, event) {
  var object = this.application_;
  if (handlerNavigate != null && handlerNavigate.length) {
    event.pageName = handlerNavigate;
    handler = 'OnRequestNavigate';
  } else if (!handlerApplication) {
    object = this.application_.GetPage();
    if (object == null) {
      console.error('Unable to route click handler - no live page.');
      return;
    }
  }

  if (!Object.getPrototypeOf(object).hasOwnProperty(handler)) {
    console.error('Unable to route click handler - method not provided.');
    console.log('Expected handler "' + handler + '" to exist on object.',
        object);

    return;
  }

  object[handler](event);

  event.preventDefault();
  return false;
};

LinkHandler.prototype.OnPeriodicUpdate = function() {};
