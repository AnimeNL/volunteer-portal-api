// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var LoginPage = function(application) {
  Page.call(this, application);
};

LoginPage.prototype = Object.create(Page.prototype);

// Attach the application login required for the login page, notably the ability
// to submit the full name to the user system.
LoginPage.prototype.OnRender = function(application, container, content) {
  var user = application.GetUser();
  if (user.isIdentified()) {
    console.error('The user has already identified as: ' + user.name);
    return;
  }

  var formElement = container.querySelector('#login-form'),
      nameElement = container.querySelector('#login-name'),
      errorElement = container.querySelector('#login-error');

  formElement.addEventListener('submit', function(event) {
    event.preventDefault();

    user.identify(nameElement.value).catch(function(error) {
      errorElement.textContent = error.message;

      nameElement.parentElement.classList.add('error');
      var changeEvent = function() {
        nameElement.parentElement.classList.remove('error');
        nameElement.removeEventListener('keydown', changeEvent);
      };

      nameElement.addEventListener('keydown', changeEvent);
    });

    return false;

  }, false);
};

// The login page uses its own layout template, since we don't want to render
// the title bar and navigation which are common on every other page.
LoginPage.prototype.GetLayoutTemplate = function() {
  return 'login';
};

// The login page has a different theme from the logged in experience, which
// means that we use a different theme color to match this accordingly.
LoginPage.prototype.GetThemeColor = function() {
  return '#22819f';
};
