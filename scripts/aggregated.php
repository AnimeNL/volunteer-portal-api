<?php
// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

$scripts = [
  'config.js',
  'content_handler.js',
  'event_location.js',
  'event_update_handler.js',
  'legacy_application.js',
  'link_handler.js',
  'menu_handler.js',
  'page.js',
  'polyfill.js',
  'ripple_handler.js',
  'schedule.js',
  'schedule_entry.js',
  'schedule_page.js',
  'single_event.js',
  'steward.js',
  'template_factory.js',
  'user.js'
];

$pages = [
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

echo 'var config = { "event-data": "", "theme-color": "", "title": "Anime 2016", "year": "2016" };';
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
