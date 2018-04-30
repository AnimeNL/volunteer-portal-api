<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['naam']) || empty($_POST['naam']) ||
    !isset($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    Header('Location: registratie.html#error');
    exit;
}

$boolean = ['tech', 'desk', 'events', 'cloakroom', 'aanwezig', 'night', 'ticket', 'girly'];
$fields = [
    'naam'              => 'Naam',
    'geboortedatum'     => 'Geboortedatum',
    'email'             => 'E-mail adres',
    'telefoonnummer'    => 'Telefoonnummer',

    'tech'              => 'Tech crew',
    'desk'              => 'Desk crew',
    'events'            => 'Event crew',
    'cloakroom'         => 'Cloakroom',

    'aanwezig'          => 'Volledig aanwezig',
    'location'          => 'Verblijfsplaats',
    'night'             => 'Nachtshifts?',
    'tshirt'            => 'T-shirtmaat',
    'girly'             => 'T-shirtmaat (girly?)',
    'ticket'            => 'Ticket',
    'social'            => 'Social media',
];


$message  = 'Iemand heeft zich aangemeld als gopher op <a href="https://gophers.team/hallo">gophers.team</a>, ';
$message .= 'via het IP adres ' . $_SERVER['REMOTE_ADDR'] . '.<br /><br />';

$message .= '<table border=1>';

foreach ($fields as $name => $label) {
    $message .= '<tr>';
    $message .= '<td><b>' . $label . '</b>:</td>';

    $value = '';
    if (in_array($name, $boolean))
        $value = array_key_exists($name, $_POST) ? 'Ja' : 'Nee';
    else
        $value = array_key_exists($name, $_POST) ? htmlspecialchars($_POST[$name]) : '<i>*leeg*</i>';

    $message .= '<td>' . $value . '</td>';
    $message .= '</tr>';
}

$message .= '</table>';

mail('gopherplanning@animecon.nl', 'Gopheraanmelding: ' . htmlspecialchars($_POST['naam']), $message,
         'From: aanmelding@gophers.team' . PHP_EOL .
         'Content-Type: text/html; charset=UTF-8');

Header('Location: registratie2.html');
