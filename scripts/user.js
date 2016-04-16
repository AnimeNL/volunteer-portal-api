// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

// The User class encapsulates knowledge about the person who is currently
// logged in to the Steward Portal. The constructor must receive a delegate
// object which supports the following methods:
//
// GetValidUserName - Should return a promise resolving the proper capitalized
//                    name when it's valid, or resolving to null when the name
//                    is not valid.
//
// ReadUserInfo     - Reads the information about the current user and returns
//                    it as an object with the {name} key.
//
// WriteUserInfo    - Writes the information about the current user. Receives a
//                    single argument: an object with the {name} key.
//
var User = function(delegate) {
  if (!Object.getPrototypeOf(delegate).hasOwnProperty('ReadUserInfo') ||
      !Object.getPrototypeOf(delegate).hasOwnProperty('WriteUserInfo') ||
      !Object.getPrototypeOf(delegate).hasOwnProperty('GetValidUserName')) {
    throw new Error('Invalid delegate supplied to the User class.');
  }

  this.delegate_ = delegate;
  this.userInfo_ = delegate.ReadUserInfo() || {
    name: null,
    hidden_events: false
  };
};

// Returns the name of the user who is identified in the current browser.
User.prototype.GetName = function() {
  return this.userInfo_.name;
};

// Whether hidden events should be shown for this user.
User.prototype.ShowHiddenEvents = function() {
  return 'hidden_events' in this.userInfo_ ?
             this.userInfo_['hidden_events'] :
             false;
}

// Updates whether hidden events should be shown for the user.
User.prototype.SetShowHiddenEvents = function(show) {
  this.userInfo_['hidden_events'] = !!show;
  this.delegate_.WriteUserInfo(this.userInfo_);
};

// Returns a promise that will be resolved when the Steward object associated
// with this user has been located in the schedule.
User.prototype.FindSteward = function() {
  var myName = this.GetName();
  if (myName == null)
    return Promise.resolve(null);

  return this.delegate_.GetSchedule().then(function(schedule) {
    var foundSteward = null;
    schedule.GetStewards().forEach(function(steward) {
      if (steward.GetName() == myName)
        foundSteward = steward;
    });

    return foundSteward;
  });
};

// Returns if the visitor has previously identified as a valid user.
User.prototype.IsIdentified = function() {
  return this.GetName() != null;
};

// Attempts to log in as |name|. Return a promise that will be resolved when
// it's known whether the login attempt was successful.
User.prototype.AttemptLogin = function(name) {
  var self = this;
  return self.delegate_.GetValidUserName(name).then(function(validName) {
    if (validName == null)
      return false;  // the username was not known for the event.

    self.userInfo_ = {
      name: validName,
      hidden_events: false
    };

    self.delegate_.WriteUserInfo(self.userInfo_);
    return true;
  });
};

// Immediately signs the user out of their current session.
User.prototype.SignOut = function() {
  this.userInfo_ = {
    name: null
  };

  this.delegate_.WriteUserInfo(this.userInfo_);
};
