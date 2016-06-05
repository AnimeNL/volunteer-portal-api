<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

function IsIgnoredShift($shift) {
    global $program;

    $name = $program[$shift['eventId']]['sessions'][0]['name'];
    if ($name === 'Group photo')
        return true;

    return false;
}

function SortByCountThenName(&$array) {
    uksort($array, function($lhs, $rhs) use ($array) {
        if ($array[$lhs] === $array[$rhs])
            return strcmp($lhs, $rhs);

        return $array[$lhs] > $array[$rhs] ? 1 : -1;
    });
}

function RenderMetrics($metrics) {
    echo '    <table class="metrics">' . PHP_EOL;
    echo '      <tbody>' . PHP_EOL;

    foreach ($metrics as $metric => $value) {
        echo '      <tr>' . PHP_EOL;
        echo '        <td>' . $metric . '</td>' . PHP_EOL;
        echo '        <td>' . $value . '</td>' . PHP_EOL;
        echo '      </tr>' . PHP_EOL;
    }

    echo '      </tbody>' . PHP_EOL;
    echo '    </table>';
}

function RenderTimeMetrics($metrics) {
    foreach ($metrics as $metric => $value) {
        $hours = floor($value);
        $minutes = floor(($value - $hours) * 60);

        $metrics[$metric] = substr('0' . $hours, 0 - strlen($hours)) . ':' . substr('0' . $minutes, -2);
    }

    RenderMetrics($metrics);
}

function CreateSlug($text) {
    $slug = strtolower($text);
    $allowed = preg_split ('//', 'abcdefghijklmnopqrstuvwxyz0123456789-+_');

    for ($i = 0, $j = strlen($slug); $i < $j; $i++) {
        if (!in_array($slug[$i], $allowed))
            $slug[$i] = '-';
    }

    $slug = preg_replace('/[\-]{2,}/s', '-', $slug);
    return preg_replace ('/^[\-]*(.+?)[\-]*$/s', '\\1', $slug);;
}
