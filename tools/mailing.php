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
// Load the template and configuration for this particular mailing.
// -------------------------------------------------------------------------------------------------

if (!array_key_exists('id', $_GET))
    die('A mailing identifier has to be provded in order to use this tool.');

if (!preg_match('/^[a-z0-9\-]+$/si', $_GET['id']))
    die('A valid mailing identifier has to be provded in order to use this tool.');

$path = __DIR__ . '/mailings/' . $environment->getHostname() . '/' . $_GET['id'] . '.php';
if (!file_exists($path))
    die('An existing mailing identifier has to be provded in order to use this tool.');

require_once($path);

if (!isset($recipients) || !is_array($recipients) ||
        !function_exists('generateHeader') || !function_exists('generateMessage')) {
    die('A valid, existing mailing identifier has to be provded in order to use this tool.');
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title><?php echo $environment->getTitle(); ?> - Mailing Tool</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" />
    </head>
    <body>
        <div class="container">
            <header class="d-flex flex-wrap justify-content-center py-3 mb-4 border-bottom">
                <a href="applications.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-dark text-decoration-none">
                    <span class="fs-4"><?php echo $environment->getTitle(); ?>: Mailing Tool</span>
                </a>
            </header>
        </div>
        <div class="album py-4 bg-light">
            <div class="container">
                <div class="container row row-cols-1 g-3">
<?php
foreach ($recipients as $recipient) {
?>
                    <div class="col-12">
                        <div class="card shadow-sm p-4">
                            <h3><?php echo generateHeader($recipient); ?></h3>
                            <textarea rows="8"><?php echo generateMessage($recipient); ?></textarea>
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
