// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var TemplateFactory = function() {
  console.error('TemplateFactory should not be instantiated.');
};

// Returns a new instance of the DOM structure contained within the template
// identified by |name|.
TemplateFactory.Get = function(name) {
  var template = document.querySelector('template#' + name);
  if (template == null) {
    console.error('Undefined template: ' + name);
    return null;
  }

  return document.importNode(template.content, true);
};
