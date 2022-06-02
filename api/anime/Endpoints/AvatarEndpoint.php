<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Endpoint;
use \Anime\EnvironmentFactory;
use \Anime\Privileges;

// Allows an avatar to be uploaded. Whether this is allowed depends on the role of the person who
// is uploading the avatar, as well as whose avatar is being uploaded.
//
// See https://github.com/AnimeNL/volunteer-portal/blob/main/API.md#apiavatar
class AvatarEndpoint implements Endpoint {
    public function validateInput(array $requestParameters, array $requestData): bool | string {
        if (!array_key_exists('authToken', $requestParameters) || !is_string($requestParameters['authToken']))
            return 'Missing parameter: authToken';

        if (!array_key_exists('event', $requestParameters) || !is_string($requestParameters['event']))
            return 'Missing parameter: event';

        if (!array_key_exists('userToken', $requestData) || !is_string($requestData['userToken']))
            return 'Missing parameter: userToken';

        if (!array_key_exists('avatar', $_FILES) || $_FILES['avatar']['type'] !== 'image/png')
            return 'Missing parameter: avatar';

        return true;  // no input is considered for this endpoint
    }

    public function execute(Api $api, array $requestParameters, array $requestData): array {
        $configuration = $api->getConfiguration();
        $currentEnvironment = $api->getEnvironment();

        $database = $api->getRegistrationDatabase(/* writable= */ false);
        if (!$database)
            return [ 'error' => 'The registration database is not available.' ];

        // Registration of the person requesting the edit.
        $editorEnvironment = null;
        $editorRegistration = null;

        // Subject of the editing request. It's possible for this to be the same as the editor.
        $subjectEnvironment = null;
        $subjectRegistration = null;

        foreach ($database->getRegistrations() as $registration) {
            if ($registration->getAuthToken() === $requestParameters['authToken']) {
                $editorEnvironment = $currentEnvironment;
                $editorRegistration = $registration;
            }

            if ($registration->getUserToken() === $requestData['userToken']) {
                $subjectEnvironment = $currentEnvironment;
                $subjectRegistration = $registration;
            }
        }

        if (!$editorRegistration)
            return [ 'error' => 'Unable to authenticate the uploader.' ];

        // Establish the privileges that have been assigned to the |$editorRegistration|, which
        // control their ability to change avatars for certain groups of volunteers.
        $privileges = Privileges::forRegistration(
            $currentEnvironment, $editorRegistration, $requestParameters['event']);

        // If |$subjectRegistration| could not be identified, it's possible that the editor has the
        // privilege to alter avatars of volunteers in other environments. Verify.
        foreach ($privileges->getEnvironments() as $environmentHostname) {
            if ($privileges->isOwnEnvironment($environmentHostname))
                continue;  // already consulted

            $environment = EnvironmentFactory::createForHostname(
                    $api->getConfiguration(), $environmentHostname);

            if (!$environment->isValid())
                continue;  // the |$environment| is invalid for some reason

            foreach ($environment->getEvents() as $environmentEvent) {
                if ($environmentEvent->getIdentifier() !== $requestParameters['event'])
                    continue;  // unrelated event

                $environmentRegistrationDatabase = $api->getRegistrationDatabaseForEnvironment(
                        $environment, /* $writable= */ false);

                if (!$environmentRegistrationDatabase)
                    continue;  // no registration database is available

                foreach ($environmentRegistrationDatabase->getRegistrations() as $registration) {
                    if ($registration->getUserToken() !== $requestData['userToken'])
                        continue;

                    $subjectEnvironment = $environment;
                    $subjectRegistration = $registration;
                    break 3;
                }
            }
        }

        // We require both |$editorRegistration| and |$subjectRegistration| to be available.
        if (!$editorRegistration || !$subjectRegistration)
            return [ 'error' => 'Unable to identify the affected registration.' ];

        // Decide whether the |$editorRegistration| has the ability to update the avatar owned by
        // the |$subjectRegistration|. This is verified through granted privileges.
        $allowed = false;

        if ($editorRegistration === $subjectRegistration) {
            $allowed = true;
        } else {
            if ($editorEnvironment === $subjectEnvironment)
                $allowed = $privileges->can(Privileges::PRIVILEGE_UPDATE_AVATAR_ENVIRONMENT);
            else
                $allowed = $privileges->can(Privileges::PRIVILEGE_UPDATE_AVATAR_ANY);
        }

        if (!$allowed)
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
