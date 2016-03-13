<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime\Services;

// Default implementation of the ServiceLog interface that will write failures to a |ERROR_LOG| and
// inform a set of people of the failure by sending e-mail messages. Writing of the log, as well as
// sending e-mail alerts, will be done lazily after the service manager's execution queue is empty.
class ServiceLogImpl implements ServiceLog {
    // File in which the service log will write error messages.
    const ERROR_LOG = __DIR__ . '/error.log';

    private $messages = [];

    // Called when the service manager has flushed the execution queue. Log any new error messages
    // to the |ERROR_LOG| file and send an alert to the people configured to receive it.
    public function onFinish() {
        if (!count($this->messages))
            return;  // everything went alright

        $contents = implode(PHP_EOL, $this->messages) . PHP_EOL;

        // Ideally we would verify that the write was successful, but there's few things we can do
        // when it failed. An alert e-mail will be send momentarily regardless.
        file_put_contents(self::ERROR_LOG, $contents, FILE_APPEND);

        // TODO: Send an alert for failed services.
    }

    // Called when the service identified by |$identifier| has finished executing. The |$runtime|
    // indicates the time taken by the service's execution routine in milliseconds. We don't log
    // successful runs, although their status can be verified by inspecting `state.json`.
    public function onServiceExecuted(string $identifier, float $runtime) {
    }

    // Called when the service identified by |$identifier| has finished executing, but was not able
    // to run successfully. The |$runtime| indicates the time taken by the service in milliseconds.
    public function onServiceFailure(string $identifier, float $runtime) {
        $this->messages[] = $this->createMessage($identifier, $runtime, 'Execution failed.');
    }

    // Called when the service identified by |$identifier| failed to execute because |$exception|
    // got thrown. The |$runtime| indicates the time taken by the service in milliseconds.
    public function onServiceException(string $identifier, float $runtime, $exception) {
        $description  = $exception->getMessage();
        $description .= ' (' . basename($exception->getFile()) . ':' . $exception->getLine() . ')';

        $this->messages[] = $this->createMessage($identifier, $runtime, $description);
    }

    // Creates the message for a result of |$identified| that took |$runtime| milliseconds.
    private function createMessage(string $identifier, float $runtime, string $description) {
        $message  = date('[Y-m-d H:i:s] ');
        $message .= '[' . sprintf('%.2f', $runtime) . 'ms] ';
        $message .= '[' . $identifier . '] ';
        $message .= $description;

        return $message;
    }
}
