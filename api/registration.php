<?php
// Copyright 2019 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting((E_ALL | E_STRICT) & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/anime/Services/generateAccessCode.php';
require __DIR__ . '/error.php';

Header('Access-Control-Allow-Origin: *');
Header('Content-Type: application/json');

// Returns whether the |$value| is truthy.
function isTruthy($value) {
    return in_array(strtolower($value), ['yes', 'ja', 'true']);
}

if ($_SERVER['REQUEST_METHOD'] != 'POST')
    dieWithError('This request method is not valid for this API.');

// Require the first name, last name and e-mail address to be present and valid.
if (!filter_input(INPUT_POST, 'firstName') || !filter_input(INPUT_POST, 'lastName'))
    dieWithError('First name and/or last name is missing.');

if (!filter_input(INPUT_POST, 'emailAddress', FILTER_VALIDATE_EMAIL))
    dieWithError('E-mail address is missing or invalid.');

$environment = \Anime\Environment::createForHostname($_SERVER['HTTP_HOST']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

$database = $environment->createDatabase(/* readOnly= */ false);

$registration = $database->findRegistrationByEmailAddress($_POST['emailAddress']);
if (!is_null($registration))
    dieWithMessage('Er is al een registratie met dit e-mailadres.');

$accessCode = rand(1000, 9999);
$displayName = $_POST['firstName'] . ' ' . $_POST['lastName'];

// (1) Send an e-mail to the volunteering staff informing them of the registration.
{
    $fieldConfiguration = [
        // name             => [ type <boolean, string>, label ]
        'firstName'         => [ 'string',  'First name' ],
        'lastName'          => [ 'string',  'Last name' ],
        'emailAddress'      => [ 'string',  'E-mail' ],
        'telephoneNumber'   => [ 'string',  'Telephone' ],
        'dateOfBirth'       => [ 'string',  'Date of birth' ],
        'fullAvailability'  => [ 'boolean', 'Fully available?' ],
        'nightShifts'       => [ 'boolean', 'Night shifts?' ],
        'socialMedia'       => [ 'boolean', 'Social media?' ],
        'dataProcessing'    => [ 'boolean', 'Data processing?' ],
    ];

    $message  = 'Iemand heeft zich aangemeld als vrijwilliger op de vrijwilligersportal via het IP ';
    $message .= 'adres ' . $_SERVER['REMOTE_ADDR'] . '.<br /><br />';
    
    $message .= '<table border=1>';
    
    foreach ($fieldConfiguration as $name => [ $type, $label ]) {
        $exists = array_key_exists($name, $_POST);
        $value = null;
    
        switch ($type) {
            case 'boolean':
                $value = $exists && isTruthy($_POST[$name]) ? 'Yes' : 'No';
                break;
            case 'string':
                $value = $exists ? $_POST[$name] : '(null)';
                break;
            default:
                throw new Exception('Invalid field type: ' . $type);
        }
    
        $message .= '<tr>';
        $message .= '<td><b>' . $label . '</b>:</td>';
        $message .= '<td>' . $value . '</td>';
        $message .= '</tr>';
    }
    
    $message .= '<tr>';
    $message .= '<td><b>Access code</b>:</td>';
    $message .= '<td>' . $accessCode . '</td>';
    $message .= '</tr>';
    
    $message .= '</table>';
    
    mail('security@animecon.nl', 'Aanmelding: ' . htmlspecialchars($displayName), $message,
             'From: aanmelding@stewards.team' . PHP_EOL .
             'Content-Type: text/html; charset=UTF-8');
}

// (2) Create a VolunteerRegistration entry on the spreadsheet.
{
    $request = new \Anime\Storage\VolunteerRegistrationRequest();
    $request->firstName = $_POST['firstName'];
    $request->lastName = $_POST['lastName'];
    $request->accessCode = rand(1000, 9999);
    $request->emailAddress = $_POST['emailAddress'];
    $request->phoneNumber = $_POST['telephoneNumber'];
    $request->nightShifts = array_key_exists('nightShifts', $_POST);

    $database->createRegistration($request);
}

// (3) Confirm the successful registration with the registrant.

echo json_encode([
    'success'       => true,
    'accessCode'    => $request->accessCode,
]);
