<?php
// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

$scripts = glob(__DIR__ . '/*.js');
$pages = glob(__DIR__ . '/pages/*.js');

Header('Content-Type: application/javascript');

echo '// =============================================================================' . PHP_EOL;
echo '// config.json' . PHP_EOL;
echo '// =============================================================================' . PHP_EOL;
echo PHP_EOL;

echo 'var config = ' . file_get_contents(__DIR__ . '/../config.json') . ';' . PHP_EOL;
echo PHP_EOL;

foreach ($scripts as $file) {
  echo '// =============================================================================' . PHP_EOL;
  echo '// scripts/' . basename($file) . PHP_EOL;
  echo '// =============================================================================' . PHP_EOL;
  echo PHP_EOL;

  echo file_get_contents($file);

  echo PHP_EOL;
}

foreach ($pages as $file) {
  echo '// =============================================================================' . PHP_EOL;
  echo '// scripts/pages/' . basename($file) . PHP_EOL;
  echo '// =============================================================================' . PHP_EOL;
  echo PHP_EOL;

  echo file_get_contents($file);

  echo PHP_EOL;
}
