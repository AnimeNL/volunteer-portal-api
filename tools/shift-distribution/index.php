<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

define('PROGRAM_FILE', __DIR__ . '/../../configuration/program.json');
define('STEWARD_PROGRAM_FILE', __DIR__ . '/../../configuration/program_stewards.json');
define('STEWARD_FILE', __DIR__ . '/../../configuration/teams/stewards.json');
define('SHIFTS_FILE', __DIR__ . '/../../configuration/shifts_stewards.json');

$program = [];
$stewards = [];
$shifts = [];

// (1) Load the normal, publicized AnimeCon program.
foreach (json_decode(file_get_contents(PROGRAM_FILE), true) as $entry)
    $program[$entry['id']] = $entry;

// (2) Load the steward-specific program additions.
foreach (json_decode(file_get_contents(STEWARD_PROGRAM_FILE), true) as $entry)
    $program[$entry['id']] = $entry;

// (3) Load the list of stewards that will participate in this event.
foreach (json_decode(file_get_contents(STEWARD_FILE), true) as $entry)
    $stewards[$entry['name']] = $entry;

// (4) Load the list of shifts that the stewards have been assigned to.
foreach (json_decode(file_get_contents(SHIFTS_FILE), true) as $steward => $stewardShifts)
    $shifts[$steward] = $stewardShifts;

// -------------------------------------------------------------------------------------------------
// Graph: number of scheduled hours per steward

$hoursPerSteward = [];

foreach ($stewards as $steward => $data) {
    $hours = 0;

    if (array_key_exists($steward, $shifts)) {
        foreach ($shifts[$steward] as $shift) {
            if ($shift['shiftType'] === 'event')
                $hours += ($shift['endTime'] - $shift['beginTime']) / 3600;
        }
    }

    $hoursPerSteward[$steward] = $hours;
}

uksort($hoursPerSteward, function($lhs, $rhs) use ($hoursPerSteward) {
    if ($hoursPerSteward[$lhs] === $hoursPerSteward[$rhs])
        return strcmp($lhs, $rhs);

    return $hoursPerSteward[$lhs] > $hoursPerSteward[$rhs] ? 1 : -1;
});

$hoursPerStewardLabels = implode("', '", array_keys($hoursPerSteward));
$hoursPerStewardValues = implode(', ', array_values($hoursPerSteward));

// -------------------------------------------------------------------------------------------------
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex" />
    <meta name="viewport" content="width=device-width, minimum-scale=1.0, initial-scale=1.0, user-scalable=no" />
    <title>Anime 2016 - Shift Distribution</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.1.4/Chart.min.js"></script>
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Roboto:400,700,400italic" />
    <link rel="stylesheet" href="shifts.css" />
  </head>
  <body>
    <h1>Scheduled hours per steward <a name="scheduled-hours-per-steward" href="#scheduled-hours-per-steward">#</a></h1>
    <canvas id="scheduled-hours-per-steward" width="800" height="300"></canvas>
    <script>
      (function() {
          var element = document.getElementById('scheduled-hours-per-steward');
          new Chart(element, {
              type: 'bar',
              options: {
                  scales: {
                      xAxes: [{ ticks: { autoSkip: false } }],
                      yAxes: [{ ticks: { beginAtZero: true } }]
                  }
              },
              data: {
                  labels: [ '<?php echo $hoursPerStewardLabels; ?>' ],
                  datasets: [
                      {
                          label: 'Scheduled hours',
                          backgroundColor: 'rgba(25, 118, 210, 0.9)',
                          data: [ <?php echo $hoursPerStewardValues; ?> ]
                      }
                  ],
              }
          });
      })();
    </script>
  </body>
</html>