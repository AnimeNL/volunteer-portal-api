// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

// The Ripple handler enables the ripple effect to be used on any element that
// has the "material-ripple" class name.
var RippleHandler = function() {
  var element = document.createElement('div');
  element.classList.add('material-ripple-effect');

  // Remove the element from the DOM after the animation has finished.
  ['animationend', 'webkitAnimationEnd', 'MSAnimationEnd'].forEach(
      function(name) {
    element.addEventListener(name, function() {
      element.classList.remove('light');
      //element.parentElement.removeChild(element);
    });
  });

  this.element_ = element;
};

// Called when new content is being added to the page. All elements with the
// appropriate class will have an onclick event added to them.
RippleHandler.prototype.OnRender = function(renderedElement) {
  var elements = renderedElement.querySelectorAll('.material-ripple');
  for (var index = 0; index < elements.length; ++index) {
    elements[index].addEventListener('click',
        RippleHandler.prototype.OnRippleElementClick.bind(this));
  }
};

// Called when an element with a ripple is being clicked on. This is where the
// ripple effect has to be created.
RippleHandler.prototype.OnRippleElementClick = function(event) {
  event.preventDefault();

  if (this.element_ == event.target)
    return;

  // Remove the ripple element from the DOM if it's attached anywhere.
  if (this.element_.parentElement)
    this.element_.parentElement.removeChild(this.element_);

  // Calculate the offset on the page based on the positioning anchestry of the
  // element that will be containing this effect.
  var offset = { x: 0, y: 0 },
      calculateOffset = function(element) {
    if (!element)
      return;

    offset.x += element.offsetLeft;
    offset.y += element.offsetTop;

    calculateOffset(element.offsetParent);
  };

  var positionValue =
      getComputedStyle(event.target, null).getPropertyValue('position');
  if (positionValue == 'relative' || positionValue == 'fixed')
    calculateOffset(event.target);
  else
    calculateOffset(event.target.offsetParent);

  var largestDimension = Math.max(event.target.offsetWidth,
                                  event.target.offsetHeight);

  this.element_.style.left = (event.pageX - offset.x - largestDimension / 2) + 'px';
  this.element_.style.top = (event.pageY - offset.y - largestDimension / 2) + 'px';

  this.element_.style.width = largestDimension + 'px';
  this.element_.style.height = largestDimension + 'px';

  if (event.target.classList.contains('light'))
    this.element_.classList.add('light');
  else
    this.element_.classList.remove('light');

  event.target.appendChild(this.element_);
};

RippleHandler.prototype.OnPeriodicUpdate = function() {};
