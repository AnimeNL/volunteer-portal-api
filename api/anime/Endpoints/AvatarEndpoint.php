<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Endpoints;

use \Anime\Api;
use \Anime\Endpoint;
use \Anime\EnvironmentFactory;

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

        // Registration of the person requesting the edit.
        $editorRegistration = null;

        // Subject of the editing request. It's possible for this to be the same as the editor.
        $subjectRegistration = null;

        if ($database) {
            $registrations = $database->getRegistrations();

            foreach ($registrations as $registration) {
                if ($registration->getAuthToken() === $requestParameters['authToken'])
                    $editorRegistration = $registration;

                if ($registration->getUserToken() === $requestData['userToken'])
                    $subjectRegistration = $registration;
            }
        }

        // If |$editorRegistration| is an administrator, there is a possibility that we have to
        // check out the other environments to find the right |$subjectRegistration|.
        if ($editorRegistration && !$subjectRegistration) {
            $role = $editorRegistration->getEventAcceptedRole($requestParameters['event']) ?? 'none';

            $isAdministrator = $editorRegistration->isAdministrator();

            $isStaff = stripos($role, 'Staff') !== false;
            $isSenior = stripos($role, 'Senior') !== false;

            $isCrossEnvironmentAllowedHost = in_array(
                $currentEnvironmentHostname, EventEndpoint::CROSS_ENVIRONMENT_HOSTS_ALLOWLIST);

            // This follows the logic in the EventEndpoint to decide whether the volunteer is able
            // to see the other environments. We really should abstract this in a function.
            if ($isAdministrator || (($isStaff || $isSenior) && $isCrossEnvironmentAllowedHost)) {
                foreach (EnvironmentFactory::getAll($configuration) as $environment) {
                    if ($environment->getHostname() === $currentEnvironment->getHostname())
                        continue;

                    foreach ($environment->getEvents() as $environmentEvent) {
                        if ($environmentEvent->getIdentifier() !== $requestData['event'])
                            continue;  // unrelated event

                        $environmentDatabase = $api->getRegistrationDatabaseForEnvironment(
                                $environment, /* $writable= */ false);

                        if (!$environmentDatabase)
                            continue;  // no environment database

                        foreach ($environmentDatabase->getRegistrations() as $registration) {
                            if ($registration->getUserToken() !== $requestData['userToken'])
                                continue;

                            $subjectRegistration = $registration;
                            break 3;
                        }
                    }
                }
            }
        }

        // We require both |$editorRegistration| and |$subjectRegistration| to be available.
        if (!$editorRegistration || !$subjectRegistration)
            return [ 'error' => 'Unable to identify the affected registrations.' ];

        $role = $editorRegistration->getEventAcceptedRole($requestParameters['event']) ?? 'none';

        // Access is granted when either:
        //     (1) The |$editorRegistration| is an administrator,
        $isAdministrator = $editorRegistration->isAdministrator();

        //     (2) The |$editorRegistration| is a senior or staff volunteer during this event,
        $isStaff = stripos($role, 'Staff') !== false;
        $isSenior = stripos($role, 'Senior') !== false;

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
