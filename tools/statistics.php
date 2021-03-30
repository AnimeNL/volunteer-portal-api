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

        foreach ($registration->getEvents() as $identifier => $participationRole) {
            if (in_array($participationRole, ['Cancelled', 'Registered', 'Rejected', 'Unregistered']))
                continue;

            if (!array_key_exists($identifier, $events)) {
                $events[$identifier] = [
                    // Participative roles, experience and tenure.
                    'roles'         => [],
                    'volunteers'    => 0,

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

            // FIXME: Amend the condition if we have volunteers who are older than a hundred years.
            $eventTime = strtotime(substr($identifier, 0, 4) . '-07-01');
            $eventAge = floor(($eventTime - $dateOfBirth) / (365 * 86400));

            if ($eventAge >= 12 && $eventAge <= 100)
                $events[$identifier]['age'][] = $eventAge;
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
    $chartData = [
        'volunteerCountData'        => [ [ '', ...array_keys($roles) ] ],
        'genderDistributionData'    => [],
        'ageDistributionData'       => [],
    ];

    foreach ($events as $identifier => $eventInformation) {
        // (2a) Volunteer count
        $chartData['volunteerCountData'][] = [
            (string)$identifier,
            ...array_map(function ($role) use ($eventInformation) {
                return array_key_exists($role, $eventInformation['roles'])
                        ? $eventInformation['roles'][$role]
                        : 0;

            }, array_keys($roles)),
        ];

        // (2b) Gender distribution

        // (2c) Age distribution

    }

?>
                    <div class="col">
                        <div id="chart-volunteer-count" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div class="card shadow-sm p-2">
                            <canvas id="chart-gender-distribution" width="598" height="300"></canvas>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card shadow-sm p-2">
                            <canvas id="chart-age-distribution" width="598" height="300"></canvas>
                        </div>
                    </div>
                    <script>
                        const volunteerCountElement = document.getElementById('chart-volunteer-count');
                        const genderDistributionElement = document.getElementById('chart-gender-distribution');
                        const ageDistributionElement = document.getElementById('chart-age-distribution');

                        google.charts.load('current', { packages: [ 'corechart', 'bar' ] });
                        google.charts.setOnLoadCallback(() => {
<?php
foreach ($chartData as $variableName => $data) {
    echo '                            const ' . $variableName . ' = google.visualization.arrayToDataTable(';
    echo json_encode($data) . ');' . PHP_EOL;
}
?>

                            const volunteerCountChart = new google.charts.Bar(volunteerCountElement);

                            volunteerCountChart.draw(volunteerCountData, {
                                colors: [ '#2979FF' ],
                                height: 300,
                                hAxes: { title: 'none' },
                                stacked: true,
                            });

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
