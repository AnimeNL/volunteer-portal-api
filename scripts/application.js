// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

const User = require('./user');

class Application {
    constructor() {
        this.user_ = new User();

        console.log('Hallo!');
    }

    get user() { return this.user_; }
}

window.application = new Application();
