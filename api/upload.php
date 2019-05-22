<?php
// Copyright 2019 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

const TARGET_DIR = __DIR__ . '/../avatars/';

const AVATAR_WIDTH_PX = 256;
const AVATAR_HEIGHT_PX = 256;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/error.php';

Header('Access-Control-Allow-Origin: *');
Header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] != 'POST')
    dieWithError('This request method is not valid for this API.');

if (!array_key_exists('authToken', $_POST) || !array_key_exists('type', $_POST))
    dieWithError('Invalid input data given to this API.');

$environment = \Anime\Environment::createForHostname($_SERVER['HTTP_HOST']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

$volunteers = $environment->loadVolunteers();
$volunteer = $volunteers->findByAuthToken($_POST['authToken']);

if ($volunteer === null)
    dieWithError('Unrecognized volunteer login information.');

// We have authenticated and found a valid |$volunteer|. Now establish the set of environments they
// have access to, as that sets boundaries on what they can manipulate.
$environments = [ $environment ];

if ($volunteer->isAdmin())
    $environments = \Anime\Environment::getAll();

// Behaviour of this system depends on the `type` that has been requested.
switch ($_POST['type']) {
    // "upload-avatar"
    //
    // Enables user avatars to be updated. This is gated on two abilities that could be granted to
    // the volunteer: "upload-avatar-self" which allows them to change their own avatar, and
    // "upload-avatar-all" which allows them to change all user avatars.
    //
    // The target user is included in the `targetUserToken` POST field. The image data for the
    // avatar itself is included in the `targetUserAvatar` POST field.
    case 'update-avatar':
        if (!array_key_exists('targetUserToken', $_POST) ||
            !array_key_exists('targetUserAvatar', $_POST)) {
            dieWithError('Invalid input data given to this API.');
        }

        $targetVolunteer = null;

        foreach ($environments as $environment) {
            $volunteers = $environment->loadVolunteers();
            if ($targetVolunteer = $volunteers->findByUserToken($_POST['targetUserToken']))
                break;
        }

        if (!$targetVolunteer)
            dieWithError('Invalid target volunteer requested.');

        // Validate that the |$volunteer| is actually allowed to update the avatar belonging to
        // |$targetVolunteer|. There are two abilities that play in to this.
        $abilities = $volunteer->getAbilities();

        if (!in_array('update-avatar-all', $abilities)) {
            if (!in_array('update-avatar-self', $abilities) || $targetVolunteer !== $volunteer)
                dieWithError('Not allowed to update the avatar for this volunteer.');
        }

        $imageData = $_POST['targetUserAvatar'];
        $imageDecodedData = null;

        switch (substr($imageData, 5, 9)) {
            case 'image/jpe':
                $imageDecodedData = @ base64_decode(substr($imageData, 23));
                break;

            case 'image/gif':
            case 'image/png':
                $imageDecodedData = @ base64_decode(substr($imageData, 22));
                break;

            default:
                dieWithError('Unrecognized image format.');
        }

        $image = @ ImageCreateFromString($imageDecodedData);
        if (!$image)
            dieWithError('Unrecognized image data.');

        $avatar = ImageCreateTrueColor(AVATAR_WIDTH_PX, AVATAR_HEIGHT_PX);

        // Resize to a more reasonable size. This way we won't have to worry about the image data
        // that volunteers upload themselves, without inflating offline storage data.
        $sourceWidth = ImageSX($image);
        $sourceHeight = ImageSY($image);

        $sourceY = 0;
        $sourceX = 0;

        if ($sourceWidth > $sourceHeight) {
            $difference = $sourceWidth - $sourceHeight;

            $sourceWidth = $sourceHeight;
            $sourceX = $difference / 2;

        } else if ($sourceHeight > $sourceWidth) {
            $difference = $sourceHeight - $sourceWidth;

            $sourceHeight = $sourceWidth;
            $sourceY = $difference / 2;
        }

        ImageCopyResampled($avatar, $image, 0, 0, $sourceX, $sourceY, AVATAR_WIDTH_PX,
                           AVATAR_HEIGHT_PX, $sourceWidth, $sourceHeight);

        ImageJPEG($avatar, TARGET_DIR . $targetVolunteer->getUserToken() . '.jpg', 85);

        echo json_encode([
            'success'        => true,
            'avatar'         => $targetVolunteer->getPhoto()
        ]);

        die();

    // "update-event"
    //
    // Used to update internal notes associated with a particular event. Such notes will be visible
    // for all volunteers using the portal. May only be used by volunteers who have the
    // "manage-event-info" ability set in their account.
    case 'update-event':
        if (!array_key_exists('eventId', $_POST) || !array_key_exists('notes', $_POST))
            dieWithError('Invalid input data given to this API.');

        if (!in_array('manage-event-info', $volunteer->getAbilities()))
            dieWithError('The authenticated user is not allowed to manage event info.');

        $eventId = intval($_POST['eventId'], 10);
        $eventNotes = $_POST['notes'];

        // Update the notes in the event notes file.
        {
            $notes = json_decode(file_get_contents(\Anime\EventData::EVENT_NOTES), true);
            if (strlen($eventNotes))
                $notes[$eventId] = $eventNotes;
            else if (array_key_exists($eventId, $notes))
                unset($notes[$eventId]);

            file_put_contents(\Anime\EventData::EVENT_NOTES, json_encode($notes));
        }

        echo json_encode([
            'success'        => true,
            'notes'          => strlen($eventNotes) ? $eventNotes : null
        ]);

        die();

    default:
        dieWithError('Unrecognized upload type.');
}

echo json_encode([
    'success'        => true,
]);
