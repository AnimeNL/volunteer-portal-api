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
// Aggregate unanswered registrations for all of the known past events.
// -------------------------------------------------------------------------------------------------

$events = [];

$registrationDatabaseSettings = $environment->getRegistrationDatabaseSettings();
if ($registrationDatabaseSettings) {
    $registrationDatabase = \Anime\Storage\RegistrationDatabaseFactory::openReadOnly(
        $cache, $registrationDatabaseSettings['spreadsheet'],
        $registrationDatabaseSettings['sheet']);

    $registrations = $registrationDatabase->getRegistrations();
    foreach ($registrations as $registration) {
        foreach ($registration->getEvents() as $identifier => $participationData) {
            if ($identifier === '2020-classic')
                continue;  // TODO: Remove this exception

            $participationRole = $participationData['role'];
            if ($participationRole !== 'Registered')
                continue;  // only care about undecided volunteers in this tool

            if (!array_key_exists($identifier, $events))
                $events[$identifier] = [];

            $events[$identifier][] = $registration;
        }
    }
}

$currentEvent = null;
if (array_key_exists('event', $_GET) && array_key_exists($_GET['event'], $events))
    $currentEvent = $_GET['event'];

// -------------------------------------------------------------------------------------------------
// Display the statistics on a page. Note that Bootstrap is used for styling.
// -------------------------------------------------------------------------------------------------

?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title><?php echo $environment->getTitle(); ?> - Applications</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" />
    </head>
    <body>
        <div class="container">
            <header class="d-flex flex-wrap justify-content-center py-3 mb-4 border-bottom">
                <a href="applications.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-dark text-decoration-none">
                    <span class="fs-4"><?php echo $environment->getTitle(); ?>: Applications</span>
                </a>

                <ul class="nav nav-pills">
<?php
foreach ($events as $identifier => $eventInformation) {
    $active = $currentEvent == $identifier ? ' active' : '';
    $link = 'applications.php?event=' . $identifier;
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
if (!$currentEvent) {
?>
                    <div class="col-12">
                    <p>
                        <strong>Please select an event to view volunteering applications for.</strong> Only
                        events with open applications are listed, so we're all good when there's nothing to
                        select.
                    </p>
</div>
<?php
} else {
    $registrations = $events[$currentEvent];
    usort($registrations, function ($lhs, $rhs) {
        return strcmp($lhs->getFirstName(), $rhs->getFirstName());
    });

    $eventStartDate = null;
    foreach ($environment->getEvents() as $environmentEvent) {
        if ($environmentEvent->getIdentifier() !== $currentEvent)
            continue;

        $eventStartDate = $environmentEvent->getDates()[0];
    }

?>
                    <div class="col-12">
                        <div class="card shadow-sm p-2">
                            <p class="m-0">
                                There are currently <strong><?php echo count($registrations); ?></strong>
                                applications for us to consider.
                            </p>
                        </div>
                    </div>
<?php
    foreach ($registrations as $registration) {
        $experience = [];
        $experienceHours = 0;

        $cancelled = [];
        $rejected = [];

        foreach ($registration->getEvents() as $identifier => $participationData) {
            if ($identifier === '2020-classic')
                continue;  // TODO: Remove this exception

            switch ($participationData['role']) {
                case 'Unregistered':
                case 'Registered':
                    // Ignore these roles.
                    break;

                case 'Cancelled':
                    $cancelled[] = $identifier;
                    break;

                case 'Rejected':
                    $rejected[] = $identifier;
                    break;

                default:
                    $experience[] = $identifier;
                    $experienceHours += $participationData['hours'] ?? 0;
                    break;
            }
        }

?>
                    <div class="col">
                        <div class="card shadow-sm p-4">
                            <h3><?php echo $registration->getFirstName() . ' ' . $registration->getLastName(); ?></h3>
                            <p>
<?php
if (count($experience) > 0) {
?>
                                <?php echo $registration->getFirstName(); ?> has already helped us
                                out for <strong><?php echo number_format($experienceHours); ?> hours</strong> in this team
<?php
    if (count($experience) > 1) {
?>
                                over <strong><?php echo count($experience); ?> events</strong>
                                (<em><?php echo implode(', ', $experience); ?></em>).
<?php
    } else {
?>
                                in <?php echo $experience[0]; ?>.
<?php
    }
} else {
?>
                                <?php echo $registration->getFirstName(); ?> is a new recruit and has not
                                helped out in this team before.
<?php
}

if (count($cancelled) === 1) {
?>
                                They cancelled their participation once, in <?php echo $cancelled[0]; ?>.
<?php
} else if (count($cancelled) > 1) {
?>
                                They cancelled <?php echo count($cancelled); ?> times
                                (<em><?php echo implode(', ', $cancelled); ?></em>).
<?php
}

if (count($rejected) === 1) {
?>
                                They were rejected once, in <?php echo $rejected[0]; ?>.
<?php
} else if (count($rejected) > 1) {
?>
                                They were rejected <?php echo count($rejected); ?> times
                                (<em><?php echo implode(', ', $rejected); ?></em>).
<?php
}

    $birthDate = date_create($registration->getDateOfBirth());
    if ($eventStartDate) {
        $eventDate = date_create($eventStartDate);
        $interval = date_diff($eventDate, $birthDate);

        if ($interval->y >= 18) {
?>
                                <?php echo $registration->getFirstName(); ?> will be 18+ during this event.
<?php
        } else {
?>
                                <?php echo $registration->getFirstName(); ?> will be <?php echo $interval->y; ?> years old during this event.
<?php
        }
    } else {
        $currentDate = date_create('now');
        $interval = date_diff($currentDate, $birthDate);

        if ($interval->y >= 18) {
?>
                                <?php echo $registration->getFirstName(); ?> is currently 18+.
<?php
        } else {
?>
                                <?php echo $registration->getFirstName(); ?> is currently <?php echo $interval->y; ?> years old.
<?php
        }
    }
?>
                            </p>
                        </div>
                    </div>
<?php
    }
}
?>
                </div>
            </div>
        </div>
    </body>
</html>
