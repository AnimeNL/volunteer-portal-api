<?php
// Copyright 2018 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

$days = [];

for ($time = $firstShift; $time < $lastShift; $time += 3600) {
    $day = date('Y-m-d', $time);

    if (!array_key_exists($day, $days))
        $days[$day] = [];

    $days[$day][] = date('G', $time);
}

$distribution = [];

foreach ($shifts as $volunteerShifts) {
    foreach ($volunteerShifts as $volunteerShift) {
        if ($volunteerShift['shiftType'] != 'event')
            continue;

        $name = null;

        // Find the program session that this shift covers.
        foreach ($program[$volunteerShift['eventId']]['sessions'] as $session) {
            if ($volunteerShift['beginTime'] > $session['end'] && $name)
                continue;

            $name = $session['name'];
        }

        if (!array_key_exists($name, $distribution))
            $distribution[$name] = [];

        $beginTime = (int) (floor($volunteerShift['beginTime'] / 3600) * 3600);
        $endTime = (int) (ceil($volunteerShift['endTime'] / 3600) * 3600);

        for ($time = $beginTime; $time < $endTime; $time += 3600) {
            if (!array_key_exists($time, $distribution[$name]))
                $distribution[$name][$time] = ['0' => 0, '30' => 0];

            if ($volunteerShift['beginTime'] <= $time)
                $distribution[$name][$time]['0']++;

            if ($volunteerShift['endTime'] >= ($time + 3600))
                $distribution[$name][$time]['30']++;
        }
    }
}

?>
    <table class="shifts-distribution">
      <thead>
        <tr class="days">
          <th><!-- Empty --></th>
<?php
foreach ($days as $time => $hours)
    echo '          <th colspan="' . (count($hours) * 2) . '">' . date('l', strtotime($time)) . '</th>' . PHP_EOL;
?>
        </tr>
        <tr class="hours">
          <th><!-- Empty --></th>
<?php
foreach ($days as $time => $hours) {
    foreach ($hours as $hour)
            echo '          <th colspan="2">' . $hour . '</th>' . PHP_EOL;
}
?>
        </tr>
      </thead>
      <tbody>
<?php
ksort($distribution);
foreach ($distribution as $name => $sessions) {
    echo '        <tr>' . PHP_EOL;
    echo '          <td>' . $name . '</td>';

    for ($time = $firstShift; $time < $lastShift; $time += 3600) {
        if (!array_key_exists($time, $sessions)) {
            echo '          <td colspan="2"></td>' . PHP_EOL;
            continue;
        }

        $division = $sessions[$time];

        // Merge the cells if the same number of volunteers service the entire shift. Otherwise
        // display two separate cells for the first and second half hourly slot.
        if ($division['0'] == $division['30']) {
            echo '          <td class="scheduled" colspan="2">' . $division['0'] . '</td>' . PHP_EOL;
        } else {
            foreach (['0', '30'] as $key) {
                if (!$division[$key])
                    echo '          <td class="split"></td>' . PHP_EOL;
                else
                    echo '          <td class="scheduled split">' . $division[$key] . '</td>' . PHP_EOL;
            }
        }
    }

    echo '        </tr>' . PHP_EOL;
}
?>
      </tbody>
    </table>
