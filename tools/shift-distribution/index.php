<?php
// Copyright 2018 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

date_default_timezone_set('Europe/Amsterdam');

define('CONFIG_FILE', __DIR__ . '/../../configuration/configuration.json');
define('PROGRAM_FILE', __DIR__ . '/../../configuration/program.json');

$data = [
    'stewards' => [
        'program'    => 'program_stewards.json',
        'members'    => 'stewards.json',
        'shifts'     => 'shifts_stewards.json'
    ],
    'gophers'  => [
        'program'    => 'program_gophers.json',
        'members'    => 'gophers.json',
        'shifts'     => 'shifts_gophers.json'
    ]
];

$team = 'stewards';

if (array_key_exists('team', $_GET) && array_key_exists($_GET['team'], $data))
    $team = $_GET['team'];

define('TEAM_PROGRAM_FILE', __DIR__ . '/../../configuration/' . $data[$team]['program']);
define('TEAM_MEMBER_FILE', __DIR__ . '/../../configuration/teams/' . $data[$team]['members']);
define('TEAM_SHIFTS_FILE', __DIR__ . '/../../configuration/' . $data[$team]['shifts']);

$sections = [
    // Table displaying the number of volunteers scheduled to be present at individual shifts over
    // the course of the festival.
    'shifts_distribution'  => '[Shifts] Distribution',
];

$configuration = json_decode(file_get_contents(CONFIG_FILE), true);
$program = [];
$volunteers = [];
$shifts = [];

// (1) Load the normal, publicized AnimeCon program.
foreach (json_decode(file_get_contents(PROGRAM_FILE), true) as $entry)
    $program[$entry['id']] = $entry;

// (2) Load the volunteer-specific program additions.
foreach (json_decode(file_get_contents(TEAM_PROGRAM_FILE), true) as $entry)
    $program[$entry['id']] = $entry;

// (3) Load the list of volunteer that will participate in this event.
foreach (json_decode(file_get_contents(TEAM_MEMBER_FILE), true) as $entry)
    $volunteers[$entry['name']] = $entry;

// (4) Load the list of shifts that the volunteer have been assigned to.
foreach (json_decode(file_get_contents(TEAM_SHIFTS_FILE), true) as $volunteer => $data)
    $shifts[$volunteer] = $data;

// -------------------------------------------------------------------------------------------------

$firstShift = PHP_INT_MAX;
$lastShift = PHP_INT_MIN;

foreach ($shifts as $volunteerShifts) {
    foreach ($volunteerShifts as $volunteerShift) {
        if ($volunteerShift['shiftType'] != 'event')
            continue;

        if ($volunteerShift['beginTime'] < $firstShift)
            $firstShift = $volunteerShift['beginTime'];
        if ($volunteerShift['endTime'] > $lastShift)
            $lastShift = $volunteerShift['endTime'];
    }
}

// -------------------------------------------------------------------------------------------------

// Requirement: the first and last shifts must begin and finish on a full hour. Extend the schedule
// if need be, because this assumption holds throughout the verification tools.
$firstShift = (int) (floor($firstShift / 3600) * 3600);
$lastShift = (int) (ceil($lastShift / 3600) * 3600);

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex" />
    <title>Anime 2018 - Scheduling Overview Tool</title>
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Roboto:400,700,400italic" />
    <link rel="stylesheet" href="shifts.css" />
  </head>
  <body>
    <h1>Anime 2018 - Shift Distribution</h1>
    <ol>
<?php
foreach ($sections as $filename => $name) {
    echo '      <li><a href="#' . $filename . '">' . $name . '</a></li>' . PHP_EOL;
}
?>
    </ol>

<?php
foreach ($sections as $filename => $name) {
    echo '    <h2 id="' . $filename . '">' . $name . '</h2>' . PHP_EOL;
    require __DIR__ . '/sections/' . $filename . '.php';
}
?>
  </body>
</html>
