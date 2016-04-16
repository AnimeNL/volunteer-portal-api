// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var LegacyUser = function(user, schedule) {
  this.user_ = user;
  this.schedule_ = schedule;
};

LegacyUser.prototype.GetName = function() { return this.user_.name; };
LegacyUser.prototype.IsIdentified = function() { return this.user_.isIdentified(); };

LegacyUser.prototype.ShowHiddenEvents = function() { return this.user_.getOption('hidden_events', false); };
LegacyUser.prototype.SetShowHiddenEvents = function(show) { this.user_.setOption('hidden_events', show); };

// Returns a promise that will be resolved when the Steward object associated
// with this user has been located in the schedule.
LegacyUser.prototype.FindSteward = function() {
  var myName = this.GetName();
  if (myName == null)
    return Promise.resolve(null);

  return this.schedule_.then(function(schedule) {
    var foundSteward = null;
    schedule.GetStewards().forEach(function(steward) {
      if (steward.GetName() == myName)
        foundSteward = steward;
    });

    return foundSteward;
  });
};

// it's known whether the login attempt was successful.
LegacyUser.prototype.AttemptLogin = function(name) { return this.user_.identify(name); };
LegacyUser.prototype.SignOut = function() { return this.user_.signOut(); };
