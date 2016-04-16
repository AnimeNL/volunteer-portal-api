// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

"use strict";

var Config = function(object) {
    var self = this;
    Object.keys(object).forEach(function(key) {
        Object.defineProperty(self, key, {
            configurable: false,
            enumerable: false,
            writable: false,
            value: object[key]
        });
    });
};
