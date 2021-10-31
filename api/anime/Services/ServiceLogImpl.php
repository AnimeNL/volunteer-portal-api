<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

use Anime\Cache;
use Anime\Configuration;

use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;

// Default implementation of the ServiceLog interface that will write execution information of the
// services to a given service log. The portal's default error handler is used for services.
class ServiceLogImpl implements ServiceLog {
    // File in which the service log will write error messages. Marked public for testing purposes.
    public const SERVICE_LOG = Cache::CACHE_PATH . '/services.log';

    private $isTest;
    private $mailer;
    private $messages;
    private $failures;

    // Initializes the default service log. A |$mailerForTesting| may be provided when running a
    // test. It has the side-effect of supressing file writes to the |ERROR_LOG|.
    public function __construct($mailerForTesting = null) {
        $this->isTest = !!$mailerForTesting;
        $this->mailer = $mailerForTesting ?: new SendmailMailer();
        $this->messages = [];
        $this->failures = 0;
    }

    // Returns the number of stored messages. Should only be used for testing purposes.
    public function getMessageCountForTesting(): int {
        return count($this->messages);
    }

    // Called when a system error occurs within the services framework.
    public function onSystemError(string $error): void {
        $this->messages[] = $this->createMessage('SYSTEM', 0, $error);
    }

    // Called when the service manager has flushed the execution queue. Log any new error messages
    // to the |ERROR_LOG| file and send an alert to the people configured to receive it.
    public function onFinish(): void {
        if (!count($this->messages))
            return;  // everything went alright

        $contents = implode(PHP_EOL, $this->messages) . PHP_EOL;

        // Only write the |$contents| to the file when this is not ran as part of a unit test.
        // Ideally we would verify that the write was successful, but there's no good fallback.
        if (!$this->isTest)
            file_put_contents(self::SERVICE_LOG, $contents, FILE_APPEND);

        // Early-return if there are no failures, because there is no need to send an alert message
        // for successful service execution.
        if ($this->failures == 0)
            return;

        // TODO: Send e-mail updates when service execution could not be completed.
    }

    // Called when the service identified by |$identifier| has finished executing. The |$runtime|
    // indicates the time taken by the service's execution routine in milliseconds. We don't log
    // successful runs, although their status can be verified by inspecting `state.json`.
    public function onServiceExecuted(string $identifier, float $runtime): void {
        $this->messages[] = $this->createMessage($identifier, $runtime, 'Executed successfully.');
    }

    // Called when the service identified by |$identifier| failed to execute because an |$exception|
    // got thrown. The |$runtime| indicates the time taken by the service in milliseconds.
    public function onServiceFailed(string $identifier, float $runtime, $exception): void {
        $description  = $exception->getMessage();
        $description .= ' (' . basename($exception->getFile()) . ':' . $exception->getLine() . ')';

        $this->messages[] = $this->createMessage($identifier, $runtime, $description);
        $this->failures++;
    }

    // Creates the message for a result of |$identified| that took |$runtime| milliseconds.
    private function createMessage(string $identifier, float $runtime, string $description): string {
        $message  = date('[Y-m-d H:i:s] ');
        $message .= '[' . sprintf('%.2f', $runtime) . 'ms] ';
        $message .= '[' . $identifier . '] ';
        $message .= $description;

        return $message;
    }
}
