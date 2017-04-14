// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

var babelify = require('babelify');
var browserify = require('browserify');
var concat = require('gulp-concat');
var fs = require('fs');
var gulp = require('gulp');
var markdown = require('gulp-markdown');
var sftp = require('gulp-sftp');
var sass = require('gulp-sass');
var source = require('vinyl-source-stream');
var through2 = require('through2');
var util = require('gulp-util');

// Transform that can be used to concatenate one or more files that start with a event identifier in
// to a single JSON file containing identifier => content mappings. The identifier format is:
//
//   <!-- Event ID: Value -->
//
// While parsing is relaxed, it's recommended that you adhere to the above format. The event ID for
// a particular shift can be found by looking at the URL while viewing an event on the portal.
function concatenateContentTransform(options) {
    var contents = {};

    return through2({ objectMode: true }, function(file, enc, callback) {
        if (file.isNull())
            return callback(null, file);

        // Only support for buffers has been implemented, as that's what gulp-markdown gives us.
        if (!file.isBuffer()) {
            this.emit('error', new util.PluginError('anime', 'Only buffers are supported.'));
            return callback();
        }

        // Identify this |file| by the path of the original input file.
        var name = file.history[0];

        var content = file.contents.toString(enc);
        var identifier = content.match(/^<!--\s*event\s*id\s*:\s*(.+?)\s*-->\s*/mi);

        // Require that the |content| leads with a Event ID comment.
        if (!identifier) {
            this.emit('error', new util.PluginError('anime', name + ' must lead with a event ID'));
            return callback();
        }

        // Store the |content| stripped of the |identifier|, and proceed with the next file.
        contents[identifier[1]] = content.substr(identifier[0].length);

        callback();

    }, function(callback) {
        fs.writeFile(options.output, JSON.stringify(contents), callback);
    });
}

// Packages the files in the //content directory in a single file fit for transport.
gulp.task('package-content', function() {
    return gulp.src('content/*.md')
        .pipe(markdown())
        .pipe(concatenateContentTransform({ output: 'content.json' }));
});

// Packages the stylesheet code in a single file after processing it with SASS.
gulp.task('package-css', function() {
    return gulp.src('style/anime.scss')
        .pipe(sass({ outputStyle: 'expanded' }).on('error', sass.logError))
        .pipe(concat('anime.css'))
        .pipe(gulp.dest('./'));
});

// Packages the JavaScript code after running it through Babel in order to be able to use ES2015.
gulp.task('package-js', function() {
    return browserify('./scripts/application.js')
        .transform(babelify, { presets: ['es2015'], plugins: ['transform-async-to-generator'] })
        .bundle()
        .pipe(source('anime.js'))
        .pipe(gulp.dest('./'));
});

// Packages all static content.
gulp.task('package', ['package-content', 'package-css', 'package-js']);

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
        './anime.css',
        './anime.js',
        './content.json',
    ];

    return gulp.src(deployFiles).pipe(sftp(deployOptions));
});
