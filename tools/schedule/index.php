<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

$schedule = json_decode(file_get_contents(__DIR__ . '/../../configuration/program.json'), true);

if (!array_key_exists('showHidden', $_GET)) {
    $schedule = array_filter($schedule, function ($event) {
       return !$event['hidden'];
    });
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex" />
    <meta name="viewport" content="width=device-width, minimum-scale=1.0, initial-scale=1.0, user-scalable=no" />
    <title>Anime 2016 - Schedule</title>
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Roboto:400,700,400italic" />
    <link rel="stylesheet" href="schedule.css" />
  </head>
  <body>
    <div id="schedule"></div>
    <script>
      var schedule = <?php echo json_encode($schedule); ?>;
    </script>
    <script src="/schedule/visualize.js"></script>
  </body>
</html>