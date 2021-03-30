<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting((E_ALL | E_STRICT) & ~E_WARNING);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';

$cache = \Anime\Cache::getInstance();
$configuration = \Anime\Configuration::getInstance();
$environment = \Anime\EnvironmentFactory::createForHostname($configuration, $_SERVER['HTTP_HOST']);

function representationWithinRange($haystack, $minimum, $maximum) {
    return array_reduce($haystack, function ($carry, $age) use ($minimum, $maximum) {
        return $age >= $minimum && $age <= $maximum ? $carry + 1
                                                    : $carry;
    }, /* initial= */ 0) / count($haystack);
}

// -------------------------------------------------------------------------------------------------
// Confirm privileged access through HTTP Authentication.
// -------------------------------------------------------------------------------------------------

if (!$environment->isValid())
    die('The hostname "' . $hostname . '" is not known as a valid environment.');

$credentials = $environment->getPrivilegedAccessCredentials();
if (!is_array($credentials))
    die('The hostname "' . $hostname . '" is not set up for this tool.');

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    Header('WWW-Authenticate: Basic realm="AnimeCon Tools"');
    Header('HTTP/1.0 401 Unauthorized');
    exit;
}

if (!array_key_exists($_SERVER['PHP_AUTH_USER'], $credentials) ||
        $credentials[$_SERVER['PHP_AUTH_USER']] !== $_SERVER['PHP_AUTH_PW']) {
    Header('HTTP/1.0 401 Unauthorized');
    die('Invalid credentials have been given, access has been denied.');
}

// -------------------------------------------------------------------------------------------------
// Gather the statistics for the current environment
// -------------------------------------------------------------------------------------------------

$events = [];
$roles = [];

$registrationDatabaseSettings = $environment->getRegistrationDatabaseSettings();
if ($registrationDatabaseSettings) {
    $registrationDatabase = \Anime\Storage\RegistrationDatabaseFactory::openReadOnly(
        $cache, $registrationDatabaseSettings['spreadsheet'],
        $registrationDatabaseSettings['sheet']);

    $registrations = $registrationDatabase->getRegistrations();
    foreach ($registrations as $registration) {
        $dateOfBirth = strtotime($registration->getDateOfBirth());
        $gender = $registration->getGender();

        $first = true;
        $participatedAnyEvent = false;
        $participatedPreviousEvent = false;

        foreach ($registration->getEvents() as $identifier => $participationRole) {
            if (in_array($participationRole, ['Cancelled', 'Registered', 'Rejected', 'Unregistered'])) {
                $participatedPreviousEvent = false;
                $first = false;
                continue;
            }

            if (!array_key_exists($identifier, $events)) {
                $events[$identifier] = [
                    // Participative roles, experience and tenure.
                    'roles'         => [],
                    'volunteers'    => 0,

                    'retained'      => 0,
                    'returned'      => 0,
                    'recruited'     => 0,

                    // Demographics.
                    'age'           => [],
                    'gender'        => [],
                ];
            }

            if (!in_array($participationRole, $roles))
                $roles[] = $participationRole;

            if (!array_key_exists($participationRole, $events[$identifier]['roles']))
                $events[$identifier]['roles'][$participationRole] = 0;

            if (!array_key_exists($gender, $events[$identifier]['gender']))
                $events[$identifier]['gender'][$gender] = 0;

            $events[$identifier]['gender'][$gender]++;
            $events[$identifier]['roles'][$participationRole]++;
            $events[$identifier]['volunteers']++;

            if ($participatedPreviousEvent)
                $events[$identifier]['retained']++;
            else if ($participatedAnyEvent)
                $events[$identifier]['returned']++;
            else
                $events[$identifier]['recruited']++;

            // FIXME: Amend the condition if we have volunteers who are older than a hundred years.
            $eventTime = strtotime(substr($identifier, 0, 4) . '-07-01');
            $eventAge = floor(($eventTime - $dateOfBirth) / (365 * 86400));

            if ($eventAge >= 12 && $eventAge <= 100)
                $events[$identifier]['age'][] = $eventAge;

            $participatedAnyEvent = true;
            $participatedPreviousEvent = true;
            $first = false;
        }
    }

    foreach ($events as $identifier => $eventInformation)
        sort($events[$identifier]['age']);
}

$currentEvent = null;
if (array_key_exists('event', $_GET) && array_key_exists($_GET['event'], $events))
    $currentEvent = $_GET['event'];

// -------------------------------------------------------------------------------------------------
// Display the statistics on a page. Note that Bootstrap and ChartJS are used for styling.
// -------------------------------------------------------------------------------------------------

$colors = [
    '#3366CC', '#DC3912', '#FF9900', '#109618', '#990099', '#3B3EAC', '#0099C6', '#DD4477',
    '#66AA00', '#B82E2E', '#316395', '#994499', '#22AA99', '#AAAA11', '#6633CC', '#E67300',
    '#8B0707', '#329262', '#5574A6', '#3B3EAC'
];

?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title><?php echo $environment->getTitle(); ?> - Statistics</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" />
        <script src="https://www.gstatic.com/charts/loader.js"></script>
    </head>
    <body>
        <div class="container">
            <header class="d-flex flex-wrap justify-content-center py-3 mb-4 border-bottom">
                <a href="statistics.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-dark text-decoration-none">
                    <span class="fs-4"><?php echo $environment->getTitle(); ?></span>
                </a>

                <ul class="nav nav-pills">
<?php
foreach ($events as $identifier => $eventInformation) {
    $active = $currentEvent == $identifier ? ' active' : '';
    $link = 'statistics.php?event=' . $identifier;
?>
                    <li class="nav-item">
                        <a href="<?php echo $link; ?>" class="nav-link<?php echo $active; ?>">
                            <?php echo $identifier . PHP_EOL; ?>
                        </a>
                    </li>
<?php
}
?>
                </ul>
            </header>
        </div>
        <div class="album py-4 bg-light">
            <div class="container">
                <div class="container row row-cols-2 g-3">
<?php
if ($currentEvent === null) {
    // ---------------------------------------------------------------------------------------------
    // Display of the overview page, statistics across individual events
    // ---------------------------------------------------------------------------------------------

    // (1) Gather unique data that should be represented in each of the graphs, then sort them based
    //     on the number of entries, to enable consistent display of data in the graphs.
    $genders = [];
    $roles = [];

    foreach ($events as $eventInformation) {
        foreach ($eventInformation['genders'] as $gender => $count) {
            if (!array_key_exists($gender, $genders))
                $genders[$gender] = $count;
            else
                $genders[$gender] += $count;
        }

        foreach ($eventInformation['roles'] as $role => $count) {
            if (!array_key_exists($role, $roles))
                $roles[$role] = $count;
            else
                $roles[$role] += $count;
        }
    }

    asort($genders);
    asort($roles);

    // (2) Prepare the chart data for each of the graphs by iterating over the event information
    //     again. Missing data values will default to zero - we populate all fields.
    $volunteerCountData = [ [ '', ...array_keys($roles) ] ];
    $volunteerRetentionData = [ [ '', 'Retained', 'Returned', 'Recruited' ] ];
    $genderDistributionData = [];
    $ageDistributionData = [ [ '', '< 20', '20—24', '25—29', '30—34', '35—40', '40 >' ] ];
    $ageAveragesData = [ [ '', 'Average age', 'Median age' ] ];

    foreach ($events as $identifier => $eventInformation) {
        // (2a) Volunteer count
        $volunteerCountData[] = [
            (string)$identifier,
            ...array_map(function ($role) use ($eventInformation) {
                return array_key_exists($role, $eventInformation['roles'])
                        ? $eventInformation['roles'][$role]
                        : 0;

            }, array_keys($roles)),
        ];

        // (2b) Volunteer retention
        $volunteerRetentionData[] = [
            (string)$identifier,
            $eventInformation['retained'] / $eventInformation['volunteers'],
            $eventInformation['returned'] / $eventInformation['volunteers'],
            $eventInformation['recruited'] / $eventInformation['volunteers'],
        ];

        // (2c) Age distribution
        $ageDistributionData[] = [
            (string)$identifier,
            representationWithinRange($eventInformation['age'], 0, 19),
            representationWithinRange($eventInformation['age'], 20, 24),
            representationWithinRange($eventInformation['age'], 25, 29),
            representationWithinRange($eventInformation['age'], 30, 34),
            representationWithinRange($eventInformation['age'], 35, 39),
            representationWithinRange($eventInformation['age'], 40, 100),
        ];

        // (2d) Age averages
        $ageAveragesData[] = [
            (string)$identifier,
            /* average= */ array_sum($eventInformation['age']) / count($eventInformation['age']),
            /* median= */ $eventInformation['age'][floor(count($eventInformation['age']) / 2)],
        ];

        // (2e) Gender distribution
    }

?>
                    <div class="col">
                        <div id="chart-volunteer-count" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div id="chart-volunteer-retention" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div id="chart-age-distribution" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div id="chart-age-averages" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div class="card shadow-sm p-2">
                            <canvas id="chart-gender-distribution" width="598" height="300"></canvas>
                        </div>
                    </div>
                    <script>
                        const volunteerCountElement = document.getElementById('chart-volunteer-count');
                        const volunteerRetentionElement = document.getElementById('chart-volunteer-retention');
                        const genderDistributionElement = document.getElementById('chart-gender-distribution');
                        const ageDistributionElement = document.getElementById('chart-age-distribution');
                        const ageAveragesElement = document.getElementById('chart-age-averages');

                        google.charts.load('current', { packages: [ 'corechart', 'bar', 'line' ] });
                        google.charts.setOnLoadCallback(() => {
                            const volunteerCountData = google.visualization.arrayToDataTable(<?php echo json_encode($volunteerCountData); ?>);
                            const volunteerCountChart = new google.charts.Bar(volunteerCountElement);
                            volunteerCountChart.draw(volunteerCountData, {
                                colors: [ '#0D47A1' ],
                                height: 300,
                                hAxes: { title: 'none' },
                                stacked: true,
                            });

                            const volunteerRetentionData = google.visualization.arrayToDataTable(<?php echo json_encode($volunteerRetentionData); ?>);
                            const volunteerRetentionChart = new google.charts.Line(volunteerRetentionElement);
                            volunteerRetentionChart.draw(volunteerRetentionData, google.charts.Line.convertOptions({
                                curveType: 'function',
                                height: 300,
                                vAxis: { format: 'percent' },
                            }));

                            const ageDistributionData = google.visualization.arrayToDataTable(<?php echo json_encode($ageDistributionData); ?>);
                            const ageDistributionChart = new google.charts.Bar(ageDistributionElement);
                            ageDistributionChart.draw(ageDistributionData, {
                                colors: [ '#0D47A1' ],
                                height: 300,
                                stacked: true,
                            });

                            const ageAveragesData = google.visualization.arrayToDataTable(<?php echo json_encode($ageAveragesData); ?>);
                            const ageAveragesChart = new google.charts.Line(ageAveragesElement);
                            ageAveragesChart.draw(ageAveragesData, google.charts.Line.convertOptions({
                                curveType: 'function',
                                height: 300,
                            }));

                        });
                    </script>
<?php
} else {
    // ---------------------------------------------------------------------------------------------
    // Display of statistics for a particular event.
    // ---------------------------------------------------------------------------------------------

    // TODO
}
?>
                </div>
            </div>
        </div>
    </body>
</html>
