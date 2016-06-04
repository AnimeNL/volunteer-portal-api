<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require_once __DIR__ . '/functions.php';

define('PROGRAM_FILE', __DIR__ . '/../../configuration/program.json');
define('STEWARD_PROGRAM_FILE', __DIR__ . '/../../configuration/program_stewards.json');
define('STEWARD_FILE', __DIR__ . '/../../configuration/teams/stewards.json');
define('SHIFTS_FILE', __DIR__ . '/../../configuration/shifts_stewards.json');

$program = [];
$stewards = [];
$shifts = [];

$stewardShifts = [];

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
foreach (json_decode(file_get_contents(SHIFTS_FILE), true) as $steward => $data) {
    $stewardShifts[$steward] = $data;
}

// -------------------------------------------------------------------------------------------------

$hoursPerSteward = [];
$typesPerSteward = [];

foreach ($stewards as $steward => $data) {
    $hours = 0;

    $typesPerSteward[$steward] = [];

    if (array_key_exists($steward, $stewardShifts)) {
        foreach ($stewardShifts[$steward] as $shift) {
            if ($shift['shiftType'] !== 'event')
                continue;

            if (IsIgnoredShift($shift))
                continue;

            $hours += ($shift['endTime'] - $shift['beginTime']) / 3600;
            $typesPerSteward[$steward][$shift['eventId']] = 1;
        }
    }

    $hoursPerSteward[$steward] = $hours;
}

foreach ($typesPerSteward as $steward => $types)
    $typesPerSteward[$steward] = count($types);

SortByCountThenName($hoursPerSteward);
SortByCountThenName($typesPerSteward);

$hoursPerStewardLabels = implode("', '", array_keys($hoursPerSteward));
$hoursPerStewardValues = implode(', ', array_values($hoursPerSteward));
$hoursPerStewardMetrics = [
    'Minimum'   => min(array_values($hoursPerSteward)),
    'Maximum'   => max(array_values($hoursPerSteward)),
    'Average'   => array_sum($hoursPerSteward) / count($hoursPerSteward),
    'Total'     => array_sum($hoursPerSteward)
];

$typesPerStewardLabels = implode("', '", array_keys($typesPerSteward));
$typesPerStewardValues = implode(', ', array_values($typesPerSteward));
$typesPerStewardMetrics = [
    'Minimum'   => min(array_values($typesPerSteward)),
    'Maximum'   => max(array_values($typesPerSteward)),
    'Average'   => round(array_sum($typesPerSteward) / count($typesPerSteward), 1)
];

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
    <p>
      <b>Notes:</b> The <i>Steward Briefing</i> and <i>Group Photo</i> shifts are ignored.
    </p>
    <h1 id="scheduled-hours-per-steward">Scheduled hours per steward <a href="#scheduled-hours-per-steward">#</a></h1>
    <div>
      <canvas id="scheduled-hours-per-steward-chart" width="800" height="300"></canvas>
      <script>
        (function() {
            var element = document.getElementById('scheduled-hours-per-steward-chart');
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
      <?php RenderTimeMetrics($hoursPerStewardMetrics); ?>
    </div>

    <h1 id="shift-types-per-steward">Shift types per steward <a href="#shift-types-per-steward">#</a></h1>
    <div>
      <canvas id="shift-types-per-steward-chart" width="800" height="300"></canvas>
      <script>
        (function() {
            var element = document.getElementById('shift-types-per-steward-chart');
            new Chart(element, {
                type: 'bar',
                options: {
                    scales: {
                        xAxes: [{ ticks: { autoSkip: false } }],
                        yAxes: [{ ticks: { beginAtZero: true } }]
                    }
                },
                data: {
                    labels: [ '<?php echo $typesPerStewardLabels; ?>' ],
                    datasets: [
                        {
                            label: 'Different shift types',
                            backgroundColor: 'rgba(25, 118, 210, 0.9)',
                            data: [ <?php echo $typesPerStewardValues; ?> ]
                        }
                    ],
                }
          });
        })();
      </script>
      <?php RenderMetrics($typesPerStewardMetrics); ?>
    </div>

<?php
foreach ($stewardShifts as $steward => $shiftData) {
    $slug = 'steward-' . CreateSlug($steward);

    $hoursPerShiftType = [];
    $hoursPerDay = ['Friday' => 0, 'Saturday' => 0, 'Sunday' => 0];

    foreach ($shiftData as $shift) {
        if ($shift['shiftType'] !== 'event')
                continue;

        if (IsIgnoredShift($shift))
            continue;

        $name = $program[$shift['eventId']]['sessions'][0]['name'];
        if (!array_key_exists($name, $hoursPerShiftType))
            $hoursPerShiftType[$name] = 0;

        $hours = ($shift['endTime'] - $shift['beginTime']) / 3600;
        $hoursPerShiftType[$name] += $hours;

        if ($shift['beginTime'] < 1465596000)
            $hoursPerDay['Friday'] += $hours;
        else if ($shift['beginTime'] < 1465682400)
            $hoursPerDay['Saturday'] += $hours;
        else
            $hoursPerDay['Sunday'] += $hours;
    }

    SortByCountThenName($hoursPerShiftType);

    $hoursPerShiftTypeLabels = implode("', '", array_keys($hoursPerShiftType));
    $hoursPerShiftTypeValues = implode(', ', array_values($hoursPerShiftType));

    $hoursPerDayValues = implode(', ', array_values($hoursPerDay));

?>
    <h1 id="<?php echo $slug; ?>">Steward: <?php echo $steward; ?> <a href="#<?php echo $slug; ?>">#</a></h1>
    <div class="steward-grid">
        <div>
            <canvas id="<?php echo $slug; ?>-chart" width="700" height="350"></canvas>
            <script>
              (function() {
                  var element = document.getElementById('<?php echo $slug; ?>-chart');
                  new Chart(element, {
                      type: 'bar',
                      options: {
                          responsive: false,
                          scales: {
                              xAxes: [{ ticks: { autoSkip: false } }],
                              yAxes: [{ ticks: { beginAtZero: true } }]
                          }
                      },
                      data: {
                          labels: [ '<?php echo $hoursPerShiftTypeLabels; ?>' ],
                          datasets: [
                              {
                                  label: 'Hours',
                                  backgroundColor: 'rgba(25, 118, 210, 0.9)',
                                  data: [ <?php echo $hoursPerShiftTypeValues; ?> ]
                              }
                          ],
                      }
                });
              })();
            </script>
        </div>
        <div>
            <canvas id="<?php echo $slug; ?>-chart2" width="700" height="350"></canvas>
            <script>
              (function() {
                  var element = document.getElementById('<?php echo $slug; ?>-chart2');
                  new Chart(element, {
                      type: 'bar',
                      options: {
                          responsive: false,
                          scales: {
                              xAxes: [{ ticks: { autoSkip: false } }],
                              yAxes: [{ ticks: { beginAtZero: true } }]
                          }
                      },
                      data: {
                          labels: [ 'Friday', 'Saturday', 'Sunday' ],
                          datasets: [
                              {
                                  label: 'Hours',
                                  backgroundColor: 'rgba(25, 118, 210, 0.9)',
                                  data: [ <?php echo $hoursPerDayValues; ?> ]
                              }
                          ],
                      }
                });
              })();
            </script>
        </div>
    </div>
<?php
}
?>

    <!-- All elements on the page should be accordions, collapsed by default -->
    <script>
      window.addEventListener('load', function() {
          var headers = document.querySelectorAll('h1');
          for (var i = 0; i < headers.length; ++i) {
            var header = headers[i];
            var container = header.nextElementSibling;

            if (container.tagName != 'DIV')
                continue;

            header.onclick = function(container) {
                return function() {
                    if (container.classList.contains('collapsed')) {
                        container.classList.remove('collapsed');
                        container.style.height = container.originalHeight + 'px';
                    } else {
                        container.classList.add('collapsed');
                        container.style.height = 0 + 'px';
                    }
                }
            }(container);

            container.originalHeight = container.offsetHeight;

            if (document.location.hash != '#' + header.id) {
                container.classList.add('collapsed');
                container.style.height = '0px';
            } else {
                container.style.height = container.offsetHeight + 'px';
            }
          }
      });
    </script>
  </body>
</html>