<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

Header('Content-Security-Policy: default-src \'self\'');

// TODO: Distinguish `debug` and `release` modes where the latter used a babelified version of the
// JavaScript code rather than the actual source files, which require a very modern browser.

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <link rel="stylesheet" href="style/shell.css" />
    <title>Anime Volunteer Portal</title>
  </head>
  <body>
    <p>Nothing to see here, move along.</p>
    <script src="scripts/require.js" data-main="scripts/main.js" async></script>
  </body>
</html>