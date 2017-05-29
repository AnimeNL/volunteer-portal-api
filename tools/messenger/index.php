<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

require __DIR__ . '/../../vendor/autoload.php';

$environment = \Anime\Environment::createForHostname($_SERVER['SERVER_NAME']);
if (!$environment->isValid())
    die('Unrecognized volunteer portal environment.');

$volunteers = $environment->loadVolunteers();
if (!($volunteers instanceof \Anime\VolunteerList))
    die('There are no known volunteers.');

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex" />
    <meta name="viewport" content="width=device-width, minimum-scale=1.0, initial-scale=1.0, user-scalable=no" />
    <title>Anime 2017 - Push Notification Messenger</title>
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Roboto:400,700,400italic" />
    <link rel="stylesheet" href="../event-scheduling-calculator/style.css" />
    <link rel="stylesheet" href="style.css" />
  </head>
  <body>
    <img src="../event-scheduling-calculator/logo.png" alt="JPOP Foundation - www.animecon.nl" />
    <section>
      <header>
        <h1>Push Notification Messenger</h1>
        <h2>Tool for sending a message directly to all subscribed devices of a particular volunteer.</h2>
      </header>
      <ol>
        <li>
          <label for="topic">Volunteer</label>
          <select id="topic">
<?php
foreach ($volunteers as $volunteer)
  echo '            <option value="' . $volunteer->getToken() . '">' . $volunteer->getName() . '</option>' . PHP_EOL;
?>
          </select>
        </li>
        <li>
          <label for="message">Message</label>
          <input type="text" id="message" />
        </li>
      </ol>
      <p>
        <input type="button" id="send-message" value="Send the message" />
      </p>
    </section>
    <section>
      <pre id="result" class="new">Waiting for a message...</pre>
    </section>
    <script>
      var topicField = document.getElementById('topic'),
          messageField = document.getElementById('message'),
          sendButton = document.getElementById('send-message'),
          result = document.getElementById('result');

      sendButton.addEventListener('click', function() {
        var topic = topicField.value;
        var message = messageField.value;

        if (!topic || isNaN(parseFloat(topic)) || !isFinite(topic)) {
          alert('Invalid topic given: ' + topic);
          return;
        }

        if (!message || !message.length) {
          alert('Invalid message given: ' + message);
          return;
        }

        sendButton.disabled = true;

        result.classList.remove('new');
        result.classList.remove('error');

        result.textContent = 'Sending message...';

        var formData = new FormData();
        formData.append('topic', topic);
        formData.append('message', message);

        fetch('send.php', {
          method: 'POST',
          credentials: 'include',
          body: formData
        }).then(response => response.text())
          .then(response => {
          result.textContent = response;
        }).catch(error => {
          result.classList.add('error');
          result.textContent = '' + error;

        }).then(() => sendButton.disabled = false);
      });

    </script>
  </body>
</html>
