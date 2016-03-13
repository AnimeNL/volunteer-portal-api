<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

use Nette\Mail\IMailer;
use Nette\Mail\Message;

class ServiceLogTest extends \PHPUnit_Framework_TestCase {
    // Verifies that the service log's error log exists and is writable by the user that's executing
    // the tests. Without these properties, the service log cannot function correctly.
    public function testErrorLogShouldBeWritable() {
        $this->assertTrue(file_exists(ServiceLogImpl::ERROR_LOG));
        $this->assertTrue(is_writable(ServiceLogImpl::ERROR_LOG));
    }

    // Verifies that error messages will generate alerts that are to be send to a configured list of
    // recipients. A fake mailing interface is injected to provide this testing, even though this
    // does not guarantee that the default SendmailMailer does what it's meant to do.
    public function testAlertMessages() {
        // @codingStandardsIgnoreStart
        // CodeSniffer does not yet understand formatting of anonymous classes.
        $mailer = new class implements IMailer {
            public $message;

            public function send(Message $message) {
                $this->message = $message;
            }
        };
        // @codingStandardsIgnoreEnd

        $serviceLog = new ServiceLogImpl($mailer);
        $serviceLog->onServiceExecuted('id-success', 0.123);
        $serviceLog->onServiceFailure('id-fails', 1.234);

        try {
            functionThatDoesNotExist();
        } catch (\Throwable $exception) {
            $serviceLog->onServiceException('id-throws', 12.345, $exception);
        }

        $serviceLog->onFinish();

        // A message must have been sent to the Mailer instance by now.
        $this->assertTrue($mailer->message instanceof Message);

        $body = $mailer->message->getBody();

        // Confirm that the |$body| mentions the identifiers of the services that failed and threw
        // an exception, as well as the log message associated with the exception.
        $this->assertContains('id-fails', $body);
        $this->assertContains('id-throws', $body);
        $this->assertContains('functionThatDoesNotExist', $body);
    }
}
