// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

// The Ripple handler enables the ripple effect to be used on any element that
// has the "material-ripple" class name.
var MenuHandler = function() {
  this.currentDropDownMenu_ = null;
  this.currentMainMenu_ = null;

  this.mainButton_ = null;
  this.mainButtonFunction_ = 'navigation';

  this.overlayElement_ = document.createElement('div');
  this.overlayElement_.classList.add('material-full-overlay');
  this.overlayElement_.addEventListener('click',
      MenuHandler.prototype.OnMenuClose.bind(this));

  this.overlayElement_.addEventListener('transitionend', function(event) {
    var element = event.target;

    if (element.parentElement && !element.classList.contains('visible'))
      element.parentElement.removeChild(element);
  });
};

// Called when new content is being added to the DOM. Finds all the elements
// which declare having a material menu, and attaches the appropriate event
// listeners to them.
MenuHandler.prototype.OnRender = function(renderedElement) {
  if (this.currentDropDownMenu_ || this.currentMainMenu_)
    this.OnMenuClose();

  if (this.mainButtonFunction_ != 'navigation')
    this.SetMainButtonFunction('navigation');

  var elements = renderedElement.querySelectorAll('[material-menu-id]');
  for (var index = 0; index < elements.length; ++index) {
    var menuElement = renderedElement.querySelector(
        '#' + elements[index].getAttribute('material-menu-id'));
    menuElement.addEventListener('transitionend', function(event) {
      if (menuElement.classList.contains('open'))
        menuElement.style.borderRadius = '2px';
    });

    elements[index].addEventListener('click',
        MenuHandler.prototype.OnDropDownMenuElementClick.bind(this,
                                                              menuElement));
  }

  var element = renderedElement.querySelector('[material-main-button]');
  if (element) {
    this.mainButton_ = element;

    var menuElement = renderedElement.querySelector('#main-menu');
    element.addEventListener('click',
        MenuHandler.prototype.OnMainMenuElementClick.bind(this, menuElement));
  }

  var element = renderedElement.querySelector('[material-main-button-function]');
  if (element && this.mainButton_) {
    this.SetMainButtonFunction(
        element.getAttribute('material-main-button-function'));
  }
};

// Resets the main menu to its original appearance and behaviour.
MenuHandler.prototype.SetMainButtonFunction = function(func) {
  this.mainButton_.classList.remove('material-icon-' + this.mainButtonFunction_);
  this.mainButton_.classList.add('material-icon-' + func);

  this.mainButtonFunction_ = func;
};

// Called when a menu element has been clicked on. The |menu| is the menu which
// should be opened, whereas |event.target| contains the element that has been
// clicked to open the menu.
MenuHandler.prototype.OnDropDownMenuElementClick = function(menu, event) {
  this.currentDropDownMenu_ = menu;

  menu.style.display = 'block';

  window.requestAnimationFrame(function() {
    menu.style.height = (menu.childElementCount * 48 + 16) + 'px';
    menu.classList.add('open');
  });

  this.overlayElement_.classList.add('visible');

  // Append the overlay element to the body so that the menu can be closed.
  if (!this.overlayElement_.parentElement)
    document.body.appendChild(this.overlayElement_);
};

// Called when the main menu should be opened. |menu| represents the element
// that should be made visible.
MenuHandler.prototype.OnMainMenuElementClick = function(menu, event) {
  if (this.mainButtonFunction_ == 'back') {
    history.back();
    return;
  }

  this.currentMainMenu_ = menu;
  this.currentMainMenu_.classList.add('visible');

  this.overlayElement_.classList.add('visible');

  if (!this.overlayElement_.parentElement) {
    var overlayElement = this.overlayElement_;
    window.requestAnimationFrame(function() {
      overlayElement.classList.add('main-menu');
    });

    document.body.appendChild(overlayElement);
  }
};

// Called when the currently shown menu should be closed.
MenuHandler.prototype.OnMenuClose = function() {
  this.overlayElement_.classList.remove('visible');

  if (this.currentDropDownMenu_) {
    this.currentDropDownMenu_.style.height = '0px';
    this.currentDropDownMenu_.style.borderRadius = '0px';

    this.currentDropDownMenu_.classList.remove('open');

    var element = this.currentDropDownMenu_;
    setTimeout(function() {
      if (!element.classList.contains('open'))
        element.style.display = 'none';

    }, 400);

  }

  if (this.currentMainMenu_) {
    this.overlayElement_.classList.remove('main-menu');
    this.currentMainMenu_.classList.remove('visible');
  }

  this.currentDropDownMenu_ = null;
  this.currentMainMenu_ = null;
};

MenuHandler.prototype.OnPeriodicUpdate = function() {};
