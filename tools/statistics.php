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

// Computes |$bucketCount| buckets of ~roughly equal size based on the |$data|. This is by no means
// supposed to be a solid algorithm for creating histogram buckets, but does the job for us.
function computeDataBuckets(array $data, int $bucketCount = 5): array {
    sort($data);

    $values = [];
    foreach ($data as $value) {
        $flooredValue = floor($value);
        if (!array_key_exists($flooredValue, $values))
            $values[$flooredValue] = 1;
        else
            $values[$flooredValue]++;
    }

    $bucketSize = count($data) / $bucketCount;
    $bucketStart = null;
    $buckets = [];

    $currentCount = 0;
    foreach ($values as $value => $count) {
        if ($bucketStart === null)
            $bucketStart = $value;

        $currentCount += $count;
        if ($currentCount < $bucketSize)
            continue;

        $buckets[] = [
            'minimum'   => $bucketStart,
            'maximum'   => $value,
            'label'     => $bucketStart . '–' . $value,
        ];

        $bucketStart = null;
        $currentCount = 0;
    }

    if ($bucketStart !== null) {
        $buckets[] = [
            'minimum'   => $bucketStart,
            'maximum'   => floor(max($data)),
            'label'     => $bucketStart . '–' . floor(max($data)),
        ];
    }

    return $buckets;
}

// Returns an array with distribution of the given |$data| over the given |$buckets|.
function bucketData(array $buckets, array $data): array {
    $distribution = array_map(fn() => 0, $buckets);
    foreach ($data as $value) {
        $normalizedValue = floor($value);

        foreach ($buckets as $bucketIndex => $bucket) {
            if ($normalizedValue < $bucket['minimum'])
                continue;

            if ($normalizedValue > $bucket['maximum'])
                continue;

            $distribution[$bucketIndex]++;
        }
    }

    return array_map(function ($count) use ($data) {
        return $count > 0 ? $count / count($data)
                          : 0;

    }, $distribution);
}

// Returns an array with the data labels for the given |$buckets|.
function bucketLabels(array $buckets): array {
    return array_map(function ($bucket) { return $bucket['label']; }, $buckets);
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

        foreach ($registration->getEvents() as $identifier => $participationData) {
            $participationRole = $participationData['role'];

            if ($identifier === '2020-classic')
                continue;  // TODO: Remove this exception

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

                    // Specifics.
                    'age'           => [],
                    'gender'        => [],
                    'hours'         => [],
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
            else if (!$first)
                $events[$identifier]['recruited']++;

            // FIXME: Amend the condition if we have volunteers who are older than a hundred years.
            $eventTime = strtotime(substr($identifier, 0, 4) . '-07-01');
            $eventAge = floor(($eventTime - $dateOfBirth) / (365 * 86400));

            if ($eventAge >= 12 && $eventAge <= 100)
                $events[$identifier]['age'][] = $eventAge;

            if ($participationData['hours'] !== null)
                $events[$identifier]['hours'][] = $participationData['hours'];

            $participatedAnyEvent = true;
            $participatedPreviousEvent = true;
            $first = false;
        }
    }

    foreach ($events as $identifier => $eventInformation) {
        sort($events[$identifier]['age']);
        sort($events[$identifier]['hours']);
    }
}

$currentEvent = null;
if (array_key_exists('event', $_GET) && array_key_exists($_GET['event'], $events))
    $currentEvent = $_GET['event'];

// -------------------------------------------------------------------------------------------------
// Display the statistics on a page. Note that Bootstrap and ChartJS are used for styling.
// -------------------------------------------------------------------------------------------------

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

    $ageDistribution = [];
    $contributionDistribution = [];

    foreach ($events as $eventInformation) {
        foreach ($eventInformation['age'] as $age)
            $ageDistribution[] = $age;

        foreach ($eventInformation['gender'] as $gender => $count) {
            if (!array_key_exists($gender, $genders))
                $genders[$gender] = $count;
            else
                $genders[$gender] += $count;
        }

        foreach ($eventInformation['hours'] as $contribution)
            $contributionDistribution[] = $contribution;

        foreach ($eventInformation['roles'] as $role => $count) {
            if (!array_key_exists($role, $roles))
                $roles[$role] = $count;
            else
                $roles[$role] += $count;
        }
    }

    arsort($genders);
    asort($roles);

    $ageBuckets = computeDataBuckets($ageDistribution);
    $contributionBuckets = computeDataBuckets($contributionDistribution);

    // (2) Prepare the chart data for each of the graphs by iterating over the event information
    //     again. Missing data values will default to zero - we populate all fields.
    $volunteerCountData = [ [ '', ...array_keys($roles) ] ];
    $volunteerRetentionData = [ [ '', 'Retained', 'Returned', 'Recruited' ] ];
    $contributionHoursData = [ [ '', ...bucketLabels($contributionBuckets) ] ];
    $contributionAveragesData = [ [ '', 'Average', 'Mean', 'Total' ] ];
    $ageDistributionData = [ [ '', ...bucketLabels($ageBuckets) ] ];
    $ageAveragesData = [ [ '', 'Average age', 'Median age' ] ];
    $genderDistributionData = [ [ '', ...array_keys($genders) ] ];
    $genderAveragesData = [ [ '', ...array_keys($genders) ] ];

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

        // (2c) Contribution distribution
        if (count($eventInformation['hours'])) {
            $contributionHoursData[] = [
                (string)$identifier,
                ...bucketData($contributionBuckets, $eventInformation['hours']),
            ];

            $contributionAveragesData[] = [
                (string)$identifier,
                array_sum($eventInformation['hours']) / count($eventInformation['hours']),
                $eventInformation['hours'][floor(count($eventInformation['hours']) / 2)],
                array_sum($eventInformation['hours']),
            ];
        }

        // (2d) Age distribution
        if (count($eventInformation['age'])) {
            $ageDistributionData[] = [
                (string)$identifier,
                ...bucketData($ageBuckets, $eventInformation['age']),
            ];

            // (2e) Age averages
            $ageAveragesData[] = [
                (string)$identifier,
                /* average= */ array_sum($eventInformation['age']) / count($eventInformation['age']),
                /* median= */ $eventInformation['age'][floor(count($eventInformation['age']) / 2)],
            ];
        }

        // (2f) Gender distribution
        $genderDistributionData[] = [
            (string)$identifier,
            ...array_map(function ($gender) use ($eventInformation) {
                return array_key_exists($gender, $eventInformation['gender'])
                        ? $eventInformation['gender'][$gender]
                        : 0;

            }, array_keys($genders)),
        ];

        // (2g) Gender averages
        $genderAveragesData[] = [
            (string)$identifier,
            ...array_map(function ($gender) use ($eventInformation) {
                return array_key_exists($gender, $eventInformation['gender'])
                        ? $eventInformation['gender'][$gender] / $eventInformation['volunteers']
                        : 0;

            }, array_keys($genders)),
        ];
    }

?>
                    <div class="col">
                        <div id="chart-volunteer-count" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div id="chart-volunteer-retention" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div id="chart-contribution-hours" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div id="chart-contribution-averages" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div id="chart-age-distribution" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div id="chart-age-averages" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div id="chart-gender-distribution" class="card shadow-sm p-4"></div>
                    </div>
                    <div class="col">
                        <div id="chart-gender-averages" class="card shadow-sm p-4"></div>
                    </div>
                    <script>
                        const volunteerCountElement = document.getElementById('chart-volunteer-count');
                        const volunteerRetentionElement = document.getElementById('chart-volunteer-retention');
                        const contributionHoursElement = document.getElementById('chart-contribution-hours');
                        const contributionAveragesElement = document.getElementById('chart-contribution-averages');
                        const ageDistributionElement = document.getElementById('chart-age-distribution');
                        const ageAveragesElement = document.getElementById('chart-age-averages');
                        const genderDistributionElement = document.getElementById('chart-gender-distribution');
                        const genderAveragesElement = document.getElementById('chart-gender-averages');

                        google.charts.load('current', { packages: [ 'corechart', 'bar', 'line' ] });
                        google.charts.setOnLoadCallback(() => {
                            const volunteerCountData = google.visualization.arrayToDataTable(<?php echo json_encode($volunteerCountData); ?>);
                            const volunteerCountChart = new google.charts.Bar(volunteerCountElement);
                            volunteerCountChart.draw(volunteerCountData, {
                                chart: { title: 'Number of volunteers' },
                                colors: [ '#0D47A1' ],
                                height: 300,
                                hAxes: { title: 'none' },
                                stacked: true,
                            });

                            const volunteerRetentionData = google.visualization.arrayToDataTable(<?php echo json_encode($volunteerRetentionData); ?>);
                            const volunteerRetentionChart = new google.charts.Line(volunteerRetentionElement);
                            volunteerRetentionChart.draw(volunteerRetentionData, google.charts.Line.convertOptions({
                                chart: { title: 'Volunteer retention' },
                                curveType: 'function',
                                height: 300,
                                vAxis: { format: 'percent' },
                            }));

                            const contributionHoursData = google.visualization.arrayToDataTable(<?php echo json_encode($contributionHoursData); ?>);
                            const contributionHoursChart = new google.charts.Bar(contributionHoursElement);
                            contributionHoursChart.draw(contributionHoursData, google.charts.Bar.convertOptions({
                                chart: { title: 'Scheduled contributions (individual, in hours)' },
                                colors: [ '#0D47A1' ],
                                height: 300,
                                stacked: true,
                                vAxis: {
                                    format: 'percent',
                                },
                            }));

                            const contributionAveragesData = google.visualization.arrayToDataTable(<?php echo json_encode($contributionAveragesData); ?>);
                            const contributionAveragesChart = new google.charts.Line(contributionAveragesElement);
                            contributionAveragesChart.draw(contributionAveragesData, {
                                chart: { title: 'Scheduled contributions (team, in hours)' },
                                curveType: 'function',
                                height: 300,
                                series: [
                                    /* average= */ { axis: 'Individual' },
                                    /* mean= */ { axis: 'Individual' },
                                    /* total= */ { axis: 'Total', color: '#90A4AE' },
                                ],
                            });

                            const ageDistributionData = google.visualization.arrayToDataTable(<?php echo json_encode($ageDistributionData); ?>);
                            const ageDistributionChart = new google.charts.Bar(ageDistributionElement);
                            ageDistributionChart.draw(ageDistributionData, google.charts.Bar.convertOptions({
                                chart: { title: 'Age demographics (individual)' },
                                colors: [ '#0D47A1' ],
                                height: 300,
                                stacked: true,
                                vAxis: {
                                    format: 'percent',
                                },
                            }));

                            const ageAveragesData = google.visualization.arrayToDataTable(<?php echo json_encode($ageAveragesData); ?>);
                            const ageAveragesChart = new google.charts.Line(ageAveragesElement);
                            ageAveragesChart.draw(ageAveragesData, google.charts.Line.convertOptions({
                                chart: { title: 'Age demographics (team)' },
                                curveType: 'function',
                                height: 300,
                            }));

                            const genderDistributionData = google.visualization.arrayToDataTable(<?php echo json_encode($genderDistributionData); ?>);
                            const genderDistributionChart = new google.charts.Bar(genderDistributionElement);
                            genderDistributionChart.draw(genderDistributionData, {
                                chart: { title: 'Gender demographics (individual)' },
                                colors: [ '#2979FF', '#FF3D00', '#00E676', '#795548' ],
                                height: 300,
                            });

                            const genderAveragesData = google.visualization.arrayToDataTable(<?php echo json_encode($genderAveragesData); ?>);
                            const genderAveragesChart = new google.charts.Line(genderAveragesElement);
                            genderAveragesChart.draw(genderAveragesData, google.charts.Line.convertOptions({
                                chart: { title: 'Gender demographics (team)' },
                                curveType: 'function',
                                height: 300,
                                vAxis: { format: '#%' },
                            }));
                        });
                    </script>
<?php
} else {
    // ---------------------------------------------------------------------------------------------
    // Display of statistics for a particular event.
    // ---------------------------------------------------------------------------------------------

    // TODO

?>
                    <div class="col">
                        <div id="chart-volunteer-roles" class="card shadow-sm p-4"></div>
                    </div>
                    <script>
                        const volunteerRolesElement = document.getElementById('chart-volunteer-roles');

                        google.charts.load('current', { packages: [ 'corechart', 'bar', 'line' ] });
                        google.charts.setOnLoadCallback(() => {

                        });
                    </script>
<?php
}
?>
                </div>
            </div>
        </div>
    </body>
</html>
