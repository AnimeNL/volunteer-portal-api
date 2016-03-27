<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

Header('Content-Security-Policy: default-src \'self\'');

// TODO: Distinguish `debug` and `release` modes where the latter used a babelified version of the
// JavaScript code rather than the actual source files, which require a very modern browser.

// TODO: Inline the `shell.css` when serving the page to make sure that it loads instantly.

// TODO: Include HTML for the application shell directly in this file.

// TODO: Load `anime.css` once the shell has seen first meaningful paint.

// TODO: Cache all required resources locally in a Service Worker.

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <link rel="stylesheet" href="style/shell.css" />
    <link rel="stylesheet" href="style/anime.css" />
    <title>Anime Volunteer Portal</title>
  </head>
  <body>
    <p>Nothing to see here, move along.</p>
    <script src="scripts/main-dev.js" async></script>
  </body>
</html>