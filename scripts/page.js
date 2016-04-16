// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var Page = function(application) {
  this.application_ = application;
  this.config_ = application.GetConfig();
};

// Returns the name of the outer layout template to apply to the page in order
// to host the template, available using GetTemplate().
Page.prototype.GetLayoutTemplate = function() {
  return 'layout';
};

// Returns the name of the template to render in the content section of the
// layout, available using GetLayoutTemplate(). May be null in case no template
// has to be used for the current page.
Page.prototype.GetTemplate = function() {
  return null;
};

// Returns the colour of the theme bar to use in browsers which support this.
Page.prototype.GetThemeColor = function() {
  return this.config_['theme-color'];
};

// Returns the title that should be given to the page.
Page.prototype.GetTitle = function() {
  return this.config_['title'];
};

// Returns a promise that will be resolved when the page is ready do be
// rendered. This is the time at which the content will be presented.
Page.prototype.PrepareRender = function() {
  return Promise.resolve();
};

// Called when the page is being rendered to the screen. |content| may be null
// when no template name has been provided by the page. The |container| will
// be the container in its current state, with the contents of the old page.
// Rendering a page must be done synchronously in order to enable scroll
// position updates for in-page navigations.
Page.prototype.OnRender = function(application, container, content) {};

// Resolves |variable| against the local state. Gives pages the opportunity to
// hand over their own content variable resolution rules.
Page.prototype.ResolveVariable = function(variable) { return '[[undefined]]'; }

// Called every so often, enabling the page to update its state.
Page.prototype.OnPeriodicUpdate = function() {};
