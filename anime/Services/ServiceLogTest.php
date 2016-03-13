<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

class ServiceLogTest extends \PHPUnit_Framework_TestCase {
    // Verifies that the service log's error log exists and is writable by the user that's executing
    // the tests. Without these properties, the service log cannot function correctly.
    public function testErrorLogShouldBeWritable() {
        $this->assertTrue(file_exists(ServiceLogImpl::ERROR_LOG));
        $this->assertTrue(is_writable(ServiceLogImpl::ERROR_LOG));
    }
}
