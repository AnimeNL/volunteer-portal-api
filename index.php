<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// Whether to run the site in `release` or `debug` mode. Release mode uses the compiled variants of
// the components, scripts and styles, whereas the debug mode uses the actual files and requires
// use of a relatively modern (2016-era) browser due to ES2016 usage.
$release = false;

// TODO: Include HTML for the application shell directly in this file.

// TODO: Load `anime.css` once the shell has seen first meaningful paint.

// TODO: Cache all required resources locally in a Service Worker.

// -------------------------------------------------------------------------------------------------

// Stylesheet of the application style that should be available in-line. New-lines and comments will
// be stripped when the application is running in release mode.
$shellStylesheet = file_get_contents(__DIR__ . '/style/shell.css');

if ($release) {
    $shellStylesheet = preg_replace('/\/\*(.+?)\*\//sm', '', $shellStylesheet);
    $shellStylesheet = preg_replace('/\s+/', ' ', $shellStylesheet);
    $shellStylesheet = trim($shellStylesheet);
}

// Entry-point of the bundle that contains all the JavaScript code.
$javascriptMainFile = $release ? 'scripts/main-compiled.js' : 'scripts/main-dev.js';

// -------------------------------------------------------------------------------------------------

if ($release) {
    // Calculate the SHA-256 hash of the shell stylesheet code so that CSP can allow it.
    $shellStyleHash = base64_encode(hash('sha256', $shellStylesheet, true));

    // Set the actual CSP header, very strict with the exception of the inline stylesheet.
    Header('Content-Security-Policy: default-src \'self\' \'sha256-' . $shellStyleHash . '\'');
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <link rel="stylesheet" href="style/anime.css" /> <!-- XXX Remove XXX -->
    <link rel="manifest" href="/manifest.json" />
    <title>Anime Volunteer Portal</title>
    <style><?php echo $shellStylesheet; ?></style>
  </head>
  <body>
    <p>Nothing to see here, move along.</p>
    <script src="<?php echo $javascriptMainFile; ?>" async></script>
  </body>
</html>