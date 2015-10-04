<?php
// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require_once __DIR__ . '/../common/parsedown.php';

setlocale(LC_ALL, 'nl_NL.utf8');

$lastUpdated = strftime('%A %e %B', filemtime(__DIR__ . '/introductie.md'));
$content = file_get_contents(__DIR__ . '/introductie.md');

?>
<!doctype html>
<html lang="nl">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, minimum-scale=1.0, initial-scale=1.0, user-scalable=no" />
    <title>Anime 2016: Steward registratieformulier</title>
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Roboto" />
    <link rel="stylesheet" href="/tools/common/layout.css" />
    <link rel="stylesheet" href="/tools/common/desktop.css" media="screen and (min-device-width: 768px)" />
    <link rel="stylesheet" href="/tools/common/mobile.css" media="screen and (max-device-width: 767px)" />
  </head>
  <body>
    <header>
      <img src="/tools/common/logo.png" alt="Anime 2016 - All Aboard!" />
    </header>
    <section>
      <h1>Steward registratieformulier</h1>
<?php echo Parsedown::instance()->text($content); ?> 
    </section>
    <footer>
      <p>
        Laatste wijziging op <?php echo $lastUpdated; ?>. <a href="https://github.com/AnimeNL/anime-2016/tree/master/tools/registration/">Broncode.</a>
      </p>
    </footer>
  </body>
</html>