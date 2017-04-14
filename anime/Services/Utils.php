<?php
// Copyright 2017 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// The Utils class is responsible for handling composer events.
class Utils {
    public static function postInstall() {
        // Create empty service log
        touch(ServiceLogImpl::ERROR_LOG);

        // Write empty state
        $serviceLog = new ServiceLogImpl();
        $serviceManager = new ServiceManager($serviceLog);
        $serviceManager->saveState();
    }
}
