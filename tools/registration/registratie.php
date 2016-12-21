<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['naam']) || empty($_POST['naam']) ||
    !empty($_POST['email']) || !isset($_POST['haarkleur']) || !filter_var($_POST['haarkleur'], FILTER_VALIDATE_EMAIL)) {
    Header('Location: registratie.html#error');
    exit;
}

$boolean = ['bhv', 'ehbo', 'stewardtraining'];
$fields = [
    'naam'              => 'Naam',
    'geboortedatum'     => 'Geboortedatum',
    'haarkleur'         => 'E-mail adres',
    'telefoonnummer'    => 'Telefoonnummer',
    'forumnickname'     => 'Forum nickname',

    'bhv'               => 'In bezit van BHV',
    'ehbo'              => 'In bezit van EBHO',
    'stewardtraining'   => 'Stewardtraining in 2015 gedaan',
    'ervaring'          => 'Overige ervaring',
];


$message  = 'Iemand heeft zich aangemeld als steward op <a href="https://stewards.club/hallo">stewards.club</a>, ';
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

mail('security@animecon.nl', 'Stewardaanmelding: ' . htmlspecialchars($_POST['naam']), $message,
         'From: aanmelding@stewards.club' . PHP_EOL .
         'Content-Type: text/html');

Header('Location: registratie2.html');
