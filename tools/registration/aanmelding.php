<?php
// Copyright 2019 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

function createSlug($text) {
    $pattern = 'Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC; [:Punctuation:] Remove; Lower();';
    return str_replace(' ', '-', transliterator_transliterate($pattern, $text));
}

function findRegistration($slug) {
    $registrations = file_get_contents(__DIR__ . '/data.json');
    $registrations = preg_replace(
        '#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#', '', $registrations);

    foreach (json_decode($registrations) as $registration) {
        if (createSlug($registration[0]) !== $slug)
            continue;

        return $registration;
    }

    return null;
}

function trainingMessage($participation) {
    return $participation
        ? 'We verwachten dat je dit jaar meedoet aan de stewardtraining'
        : 'Je hoeft dit jaar niet mee te doen met de stewardtraining, maar als je toch interesse ' .
          'hebt dan nodigen we je natuurlijk graag uit als er beschikbaarheid is';
}

function replaceRegistrationPlaceholders($content, $slug) {
    $registration = findRegistration($slug);
    if (!$registration)
        exit;

    $substitutions = [
        '{{name}}'       => $registration[0],
        '{{email}}'      => $registration[1],
        '{{tel}}'        => $registration[2],
        '{{training}}'   => trainingMessage($registration[6])
    ];

    return str_replace(array_keys($substitutions), array_values($substitutions), $content);
}

$boolean = ['training_no', 'training_05_25', 'training_05_26', 'training_06_01', 'training_06_02', 'hotel', 'gdpr'];
$fields = [
    'naam'                => 'Naam',
    'email'               => 'E-mailadres',
    'training_no'         => 'Training (nee)',
    'training_05_25'      => 'Training (25 mei)',
    'training_05_26'      => 'Training (26 mei)',
    'training_06_01'      => 'Training (1 juni)',
    'training_06_02'      => 'Training (2 juni)',
    'hotel'               => 'Inntel',
    'gdpr'                => 'GDPR'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && array_key_exists('naam', $_POST)) {
    $message  = 'Iemand heeft het aanmeldformulier ingevuld op <a href="https://stewards.team/hallo">stewards.team</a>, ';
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

    mail('security@animecon.nl', 'Gegevens: ' . htmlspecialchars($_POST['naam']), $message,
             'From: aanmelding@stewards.team' . PHP_EOL .
             'Content-Type: text/html; charset=UTF-8');

    Header('Location: aanmelding2.html');
}
