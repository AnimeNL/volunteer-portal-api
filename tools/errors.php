<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting((E_ALL | E_STRICT) & ~E_WARNING);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';

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
// Read the most recent errors from the log file using the `tail` utility.
// -------------------------------------------------------------------------------------------------

// Number of errors to read.
$errorCount = 10;

// Retrieve the data by executing the `tail` command, then reverse the result.
$errorData = shell_exec('tail -n' . $errorCount . ' ' . \Anime\ErrorHandler::ERROR_LOG);
$errorDataInOrder = array_reverse(explode(PHP_EOL, trim($errorData)));

// Format each of the error results in a more nicely structured array.
$errors = [];

foreach ($errorDataInOrder as $line) {
    if (strpos($line, ' ') === false)
        continue;  // invalid line

    [ $timestamp, $context ] = explode(' ', trim($line), 2);

    $errors[] = [
        'timestamp'     => $timestamp,
        'context'       => json_decode($context, true),
    ];
}

// -------------------------------------------------------------------------------------------------
// Display the statistics on a page. Note that Bootstrap is used for styling.
// -------------------------------------------------------------------------------------------------

?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title><?php echo $environment->getTitle(); ?> - Error logs</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" />
    </head>
    <body>
        <div class="container">
            <header class="d-flex flex-wrap justify-content-center py-3 mb-4 border-bottom">
                <a href="errors.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-dark text-decoration-none">
                    <span class="fs-4"><?php echo $environment->getTitle(); ?>: Error logs</span>
                </a>
            </header>
        </div>
        <div class="album py-4 bg-light">
            <div class="container">
                <div class="container row row-cols-1 g-3">
<?php
foreach ($errors as $error) {
    $message = json_encode($error['context'], JSON_PRETTY_PRINT);
    $message = str_replace('\\/', '/', $message);

?>
                    <div class="col-12">
                        <div class="card shadow-sm p-4">
                            <h3><?php echo date('l, F jS, Y @ g:i A', $error['timestamp']); ?></h3>
                            <pre><?php echo $message; ?></pre>
                        </div>
                    </div>
<?php
}
?>
                </div>
            </div>
        </div>
    </body>
</html>
