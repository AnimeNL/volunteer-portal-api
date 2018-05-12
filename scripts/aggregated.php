<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

$scripts = [
  'config.js',
  'polyfill.js',

  'content_handler.js',
  'event_update_handler.js',
  'legacy_application.js',
  'link_handler.js',
  'menu_handler.js',
  'page.js',
  'ripple_handler.js',
  'schedule_page.js',
  'template_factory.js',
];

$pages = [
  'pages/event_details_page.js',
  'pages/event_page.js',
  'pages/floor_page.js',
  'pages/location_page.js',
  'pages/login_page.js',
  'pages/overview_page.js',
  'pages/steward_overview_page.js',
  'pages/stewards_page.js'
];

Header('Content-Type: application/javascript');

echo '// =============================================================================' . PHP_EOL;
echo '// config.json' . PHP_EOL;
echo '// =============================================================================' . PHP_EOL;
echo PHP_EOL;

echo 'var config = { "event-data": "", "theme-color": "", "title": "Anime 2018", "year": "2018" };';
echo PHP_EOL . PHP_EOL;

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
