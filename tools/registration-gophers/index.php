<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require_once __DIR__ . '/../common/parsedown.php';

setlocale(LC_ALL, 'nl_NL.utf8');

$page  = 'index';
$pages = [
  'index'        => 'introductie.md',
  'registratie'  => 'registratie.md',
  'registratie2' => 'registratie-gedaan.md',
];

if (isset ($_GET['page']) && array_key_exists($_GET['page'], $pages))
  $page = $_GET['page'];

$lastUpdated = strftime('%A %e %B', filemtime(__DIR__ . '/' . $pages[$page]));
$content = file_get_contents(__DIR__ . '/' . $pages[$page]);

?>
<!doctype html>
<html lang="nl">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, minimum-scale=1.0, initial-scale=1.0, user-scalable=no" />
    <title>Anime 2018: Gopher registratieformulier</title>
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Roboto" />
    <link rel="stylesheet" href="/tools/common/layout.css" />
    <link rel="stylesheet" href="/tools/common/desktop.css" media="screen and (min-device-width: 768px)" />
    <link rel="stylesheet" href="/tools/common/mobile.css" media="screen and (max-device-width: 767px)" />
    <link rel="stylesheet" href="/tools/registration/registration.css" />
  </head>
  <body>
    <header>
      <img src="/tools/common/logo.png" alt="Anime 2018 - Queens of the Round Table!" />
    </header>
    <section>
<?php echo Parsedown::instance()->text($content); ?>
    </section>
    <footer>
      <p>
        Laatste wijziging op <?php echo $lastUpdated; ?>. <a href="https://github.com/AnimeNL/anime-2017/tree/master/tools/registration-gophers/">Broncode.</a>
      </p>
    </footer>
  </body>
</html>
