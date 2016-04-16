// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

var babelify = require('babelify');
var browserify = require('browserify');
var gulp = require('gulp');
var source = require('vinyl-source-stream');

gulp.task('package', function() {
    return;  // disabled for now

    return browserify('./scripts/main.js')
        .transform(babelify, { presets: ['es2015'] })
        .bundle()
        .pipe(source('main-compiled.js'))
        .pipe(gulp.dest('scripts/'));
});
