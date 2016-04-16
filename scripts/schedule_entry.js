// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var ScheduleEntry =
    function(startDate, endDate, title, description, navigate, className) {
  this.startDate_ = startDate;
  this.endDate_ = endDate;

  this.title_ = title;
  this.description_ = description;

  this.navigate_ = navigate;
  this.className_ = className;
};

ScheduleEntry.prototype.GetStartDate = function() { return this.startDate_; }
ScheduleEntry.prototype.GetEndDate   = function() { return this.endDate_; }

ScheduleEntry.prototype.GetTitle       = function() { return this.title_; }
ScheduleEntry.prototype.GetDescription = function() { return this.description_; }

ScheduleEntry.prototype.GetNavigate = function() { return this.navigate_; }
ScheduleEntry.prototype.GetClassName = function() { return this.className_; }

ScheduleEntry.prototype.IsActive = function() {
  return GetCurrentDate().getTime() >= this.startDate_.getTime() &&
         GetCurrentDate().getTime() <  this.endDate_.getTime();
};

ScheduleEntry.prototype.IsPast = function() {
  return GetCurrentDate().getTime() >= this.endDate_.getTime();
};
