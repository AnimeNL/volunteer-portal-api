<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting((E_ALL | E_STRICT) & ~E_WARNING);
ini_set('display_errors', 1);

// -------------------------------------------------------------------------------------------------
// This tool provides an overview of the volunteering teams available in the AnimeCon organisation,
// specifically those backed by this tool. It pulls information from the backend, so will generally
// be up-to-date with the latest information and availability.
// -------------------------------------------------------------------------------------------------

require __DIR__ . '/../vendor/autoload.php';

$configuration = \Anime\Configuration::getInstance();
$environments = \Anime\EnvironmentFactory::getAll($configuration);

$teams = [
    'gophers.team'  => [
        'title'         => 'Gophers',
        'class'         => 'gophers',
        'event'         => null,

        'description'   =>
            'Gophers are responsible for delivering an incredible experience to our visitors, ' .
            'starting from welcoming them at the registration desk upon arrival, to manning the ' .
            'bag room, video rooms, and many other jobs behind the scenes.',
    ],
    'hosts.team'    => [
        'title'         => 'Festival Hosts',
        'class'         => 'hosts',
        'event'         => null,

        'description'   =>
            'Festival Hosts are responsible for making sure that our guests are met with great ' .
            'memorable impressions, making them feel welcome, and that all their questions are ' .
            'answered while moving around on the festival grounds.',
    ],
    'stewards.team' => [
        'title'         => 'Stewards',
        'class'         => 'stewards',
        'event'         => null,

        'description'   =>
            'Stewards are the first line of defense when trouble arises, and responsible for ' .
            'crowd management, safety checks, escorting special guests and assisting our ' .
            'security and first aid teams before and during the festival.',
    ]
];

foreach ($environments as $environment) {
    $latestAvailableEvent = null;
    $latestJoinableEvent = null;

    foreach ($environment->getEvents() as $event) {
        if ($event->enableRegistration())
            $latestJoinableEvent = $event;
        else if ($event->enableContent())
            $latestAvailableEvent = $event;
    }

    $teams[$environment->getHostname()]['event'] = $latestJoinableEvent ?? $latestJoinableEvent ?? null;
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap" />
    <title>AnimeCon Volunteering Opportunities</title>
    <style>
      body, html { margin: 0px; padding: 0px; }
      body {
        background-color: #CFD8DC;
        padding-bottom: 2rem;

        font-family: Roboto;
        font-size: 16px;
      }
      header, main {
        padding: 2rem 2rem 0 2rem;

        display: flex;
        justify-content: space-evenly;
      }

      header > object {
        margin: auto;
        width: 256px;
      }

      main {
        flex-wrap: wrap;
        row-gap: 16px;
      }

      article {
        background-color: white;
        box-shadow: rgb(0 0 0 / 20%) 0px 2px 1px -1px,
                    rgb(0 0 0 / 14%) 0px 1px 1px 0px,
                    rgb(0 0 0 / 12%) 0px 1px 3px 0px;

        border-radius: 8px;

        width: 320px;
        width: min(320px, 90%);
      }

      article.gophers  h1, article.gophers  > .team-link { background-color: #412c26; }
      article.hosts    h1, article.hosts    > .team-link { background-color: #5f0937; }
      article.stewards h1, article.stewards > .team-link { background-color: #212c6f; }

      article a { text-decoration: none; }
      article h1 {
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;

        font-family: Roboto, Helvetica, Arial, sans-serif;
        font-weight: 400;
        font-size: 1.5rem;

        padding: .5rem 1rem;
        margin: 0;
        color: white;
      }

      article > p {
        padding: 1rem;
        margin: 0;
      }

      article > p.team-idle {
        margin: 0 1rem 1rem 1rem;
        border: 1px dotted #546E7A;
        color: #546E7A;
        font-style: italic;
      }

      article > .team-link {
        display: inline-block;
        margin: 0 1rem 1rem 1rem;
        padding: .5rem 1rem;
        color: white;
        border-radius: 8px;
      }
    </style>
  </head>
  <body>
    <header>
      <object type="image/svg+xml" alt="J-POP Logo"
              data="/images/logo.svg?color=%230d3e59&title=Volunteering+Teams"></object>
    </header>
    <main>
<?php
foreach ($teams as $hostname => $info) {
?>
      <article class="<?php echo $info['class']; ?>">
        <a href="https://<?php echo $hostname; ?>">
          <h1><?php echo $info['title']; ?></h1>
        </a>
        <p>
          <?php echo $info['description'] . PHP_EOL; ?>
        </p>
<?php

    if (is_null($info['event'])) {
?>
        <p class="team-idle">
          There are no events that this team is recruiting for at the moment.
        </p>
<?php
    } else if ($info['event']->enableRegistration()) {
        $link = 'https://' . $hostname . '/registration/' . $info['event']->getIdentifier() . '/';
?>
        <a class="team-link" href="<?php echo $link; ?>">
          Apply to join the <?php echo $info['title']; ?>!
        </a>
<?php
    } else {
?>
        <a class="team-link" href="https://gophers.team/registration/<?php echo $teams['gophers.team']->getIdentifier(); ?>/">
          Learn more about the <?php echo $info['title'] . PHP_EOL; ?>
        </a>
<?php
    }
?>
      </article>
<?php
}
?>
    </main>
  </body>
</html>
