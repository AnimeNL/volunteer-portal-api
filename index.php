<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require_once __DIR__ . '/vendor/autoload.php';

$environment = \Anime\Environment::createForHostname($_SERVER['SERVER_NAME']);
if (!$environment->isValid())
  die('This domain name has not been configured for the volunteer portal.');

$templates = [
  'event-page.html',
  'floors-page.html',
  'identification.html',
  'layout.html',
  'overview-page.html',
  'schedule-page.html',
  'stewards-page.html'
];

// -------------------------------------------------------------------------------------------------

// Set the actual CSP header, very strict with the exception of the inline stylesheet.
Header('Content-Security-Policy: default-src \'self\' https://fcm.googleapis.com');

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex" />
    <meta name="application-name" content="<?php echo $environment->getShortName(); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="msapplication-TileColor" content="#093565" />
    <link rel="apple-touch-icon-precomposed" href="/images/logo-128.png" />
    <link rel="manifest" href="/manifest.json" />
    <link rel="stylesheet" href="/anime.css" />
    <link rel="stylesheet" href="/anime-legacy.css" />
    <title>Anime Volunteer Portal</title>
  </head>
  <body>
    <div class="container"></div>
<?php
foreach ($templates as $file)
  include(__DIR__ . '/templates/' . $file);
?>
    <script src="/anime-legacy.js"></script>
    <script src="/anime.js"></script>
    <noscript>
      <p>
        Sorry, but you need a browser which supports JavaScript in order to use this site!
      </p>
    </noscript>
  </body>
</html>
