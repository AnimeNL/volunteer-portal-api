// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

var babelify = require('babelify');
var browserify = require('browserify');
var fs = require('fs');
var gulp = require('gulp');
var sftp = require('gulp-sftp');
var source = require('vinyl-source-stream');

// Packages the JavaScript code after running it through Babel in order to be able to use ES2015.
gulp.task('package', function() {
    return browserify('./scripts/application.js')
        .transform(babelify, { presets: ['es2015'] })
        .bundle()
        .pipe(source('anime.js'))
        .pipe(gulp.dest('./'));
});

// Deploys the packaged files to the server. Requires Sublime SFTP to be set up in the project.
gulp.task('deploy', ['package'], function() {
    var sublimeConfig = fs.readFileSync('sftp-config.json').toString();

    // The Sublime SFTP configuration file allows comments in its JSON. Remove it, and convert it
    // to a JavaScript object to be consumed by the SFTP option.
    sublimeConfig = sublimeConfig.replace(/\/\*[\s\S]*?\*\/|([^:]|^)\/\/.*$/gm, '$1');
    sublimeConfig = JSON.parse(sublimeConfig);

    var deployOptions = {
        host: sublimeConfig.host,
        port: sublimeConfig.port,
        user: sublimeConfig.user,
        remotePath: sublimeConfig.remote_path,

        agent: 'pageant',
        key: sublimeConfig.ssh_key_file
    };

    var deployFiles = [
        './anime.js'
    ];

    return gulp.src(deployFiles).pipe(sftp(deployOptions));
});
