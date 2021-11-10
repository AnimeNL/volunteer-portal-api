<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Endpoint;

// Allows an avatar to be uploaded. Whether this is allowed depends on the role of the person who
// is uploading the avatar, as well as whose avatar is being uploaded.
//
// See https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apiavatar
class AvatarEndpoint implements Endpoint {
    public function validateInput(array $requestParameters, array $requestData): bool | string {
        if (!array_key_exists('authToken', $requestData) || !is_string($requestData['authToken']))
            return 'Missing parameter: authToken';

        if (!array_key_exists('userToken', $requestData) || !is_string($requestData['userToken']))
            return 'Missing parameter: userToken';

        if (!array_key_exists('event', $requestData) || !is_string($requestData['event']))
            return 'Missing parameter: event';

        if (!array_key_exists('avatar', $_FILES) || $_FILES['avatar']['type'] !== 'image/png')
            return 'Missing parameter: avatar';

        return true;  // no input is considered for this endpoint
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        $configuration = $api->getConfiguration();
        $database = $api->getRegistrationDatabase(/* writable= */ false);

        // Registration of the person requesting the edit.
        $editorRegistration = null;

        // Subject of the editing request. It's possible for this to be the same as the editor.
        $subjectRegistration = null;

        if ($database) {
            $registrations = $database->getRegistrations();

            foreach ($registrations as $registration) {
                if ($registration->getAuthToken() === $requestData['authToken'])
                    $editorRegistration = $registration;

                if ($registration->getUserToken() === $requestData['userToken'])
                    $subjectRegistration = $registration;
            }
        }

        // We require both |$editorRegistration| and |$subjectRegistration| to be available.
        if (!$editorRegistration || !$subjectRegistration)
            return [ 'error' => 'Unable to identify the affected registrations.' ];

        $editorRole = $editorRegistration->getEventAcceptedRole($requestData['event']) ?? 'none';

        // Access is granted when either:
        //     (1) The |$editorRegistration| is an administrator,
        $isAdministrator = $editorRegistration->isAdministrator();

        //     (2) The |$editorRegistration| is a senior or staff volunteer during this event,
        $isSenior = str_contains($editorRole, 'Senior');
        $isStaff = str_contains($editorRole, 'Staff');

        //     (3) The |$editorRegistration| and |$subjectRegistration| are the same person.
        $isSamePerson = $editorRegistration === $subjectRegistration;

        if (!($isAdministrator || ($isSenior || $isStaff) || $isSamePerson))
            return [ 'error' => 'You are not allowed to update this avatar.' ];

        // Now that we know the |$editorRegistration| is allowed to change the subject's avatar, do
        // the magic based on the upload, but only if it's a valid PNG file.
        $uploadedImage = ImageCreateFromPNG($_FILES['avatar']['tmp_name']);
        $avatarImage = ImageCreateTrueColor(250, 250);

        if ($uploadedImage && $avatarImage) {
            ImageCopyResampled(
                $avatarImage, $uploadedImage, 0, 0, 0, 0, 250, 250, ImageSX($uploadedImage),
                ImageSY($uploadedImage));

            $avatarPath = $subjectRegistration->getAvatarFileSystemPath();
            if (!file_exists($avatarPath) || is_writable($avatarPath)) {
                ImagePNG($avatarImage, str_replace('.jpg', '.png', $avatarPath));
                ImageJPEG($avatarImage, $avatarPath, 90);
            }
        }

        return [ 'success' => 'The avatar has been updated.' ];
    }
}
