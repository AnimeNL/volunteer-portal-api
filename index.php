<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require_once __DIR__ . '/vendor/autoload.php';

$environment = \Anime\Environment::createForHostname($_SERVER['HTTP_HOST']);
if (!$environment->isValid())
  die('This domain name has not been configured for the volunteer portal.');

$templates = [
  'event-page.html',
  'floors-page.html',
  'layout.html',
  'login.html',
  'overview-page.html',
  'schedule-page.html',
  'stewards-page.html'
];

// -------------------------------------------------------------------------------------------------

// Stylesheet of the application style that should be available in-line. New-lines and comments will
// be stripped when the application is running in release mode.
$shellStylesheet = file_get_contents(__DIR__ . '/style/shell.css');

$shellStylesheet = preg_replace('/\/\*(.+?)\*\//sm', '', $shellStylesheet);
$shellStylesheet = preg_replace('/\s+/', ' ', $shellStylesheet);
$shellStylesheet = trim($shellStylesheet);

// -------------------------------------------------------------------------------------------------

// Calculate the SHA-256 hash of the shell stylesheet code so that CSP can allow it.
$shellStyleHash = base64_encode(hash('sha256', $shellStylesheet, true));

// Set the actual CSP header, very strict with the exception of the inline stylesheet.
Header('Content-Security-Policy: default-src \'self\' \'sha256-' . $shellStyleHash . '\'');

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <link rel="manifest" href="/manifest.json" />
    <link rel="stylesheet" href="/style/aggregated.php" />
    <title>Anime Volunteer Portal</title>
    <style><?php echo $shellStylesheet; ?></style>
  </head>
  <body>
    <div class="container initial"></div>
<?php
foreach ($templates as $file)
  include(__DIR__ . '/templates/' . $file);
?>
    <script src="/scripts/aggregated.php"></script>
    <script src="/anime.js"></script>
    <noscript>
      <p>
        Sorry, but you need a browser which supports JavaScript in order to use this site!
      </p>
    </noscript>
  </body>
</html>