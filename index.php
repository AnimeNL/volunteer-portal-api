<?php
// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// Since no external resources are being used, impose a strict content security policy.
Header('Content-Security-Policy: default-src \'self\'');

// Calculate the number of days until Anime 2016 will kick off.
$days = round((mktime(12, 0, 0, 6, 10, 2016) - time()) / 86400);

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, minimum-scale=1.0, initial-scale=1.0, user-scalable=no" />
    <title>Anime Volunteer Portal</title>
    <link rel="stylesheet" href="/anime.css" />
  </head>
  <body>
    <div class="splash" id="splash-screen">
      <header>
        Anime 2016
      </header>
      <p>
        Anime 2016 will kick off in <strong><?php echo $days; ?></strong> days.
      </p>
    </div>
  </body>
</html>