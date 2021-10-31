<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime;

use \Anime\Cache;
use \Anime\Configuration;
use \Error;

// Error handler for the AnimeCon Volunteer Portal system. Enables display of the errors to the
// regular browser output or to the console (if applicable), as well as the ability to alert the
// owners of this portal by e-mail whenever those issues occur.
//
// This error handler has to be manually installed by calling the static method. All other behaviour
// should be considered private, and no instances of this class should be made elsewhere.
class ErrorHandler {
    private const ERROR_DISPLAY = 1;
    private const ERROR_REPORTING = ((E_ALL | E_STRICT) & ~E_WARNING);

    // Full path to the log in which errors will be written. Each entry is written on its own line,
    // and will be in the format of "TIMESTAMP JSON". Entries will be appended to the file.
    public const ERROR_LOG = Cache::CACHE_PATH . '/error.log';

    public static function Install() {
        ini_set('display_errors', self::ERROR_DISPLAY);
        ini_set('error_reporting', self::ERROR_REPORTING);

        $handler = new self;

        set_error_handler([ $handler, 'onError' ]);
        set_exception_handler([ $handler, 'onException' ]);
    }

    // Called when a PHP error (at any level) occurs, with the given information. Not every error is
    // considered to be fatal. Error information will be written to an error file.
    public function onError($errno, $errstr, $errfile, $errline) {
        $translation = [
            E_ERROR              => [ true,  'E_ERROR' ],
            E_WARNING            => [ false, 'E_WARNING' ],
            E_PARSE              => [ false, 'E_PARSE' ],
            E_NOTICE             => [ false, 'E_NOTICE' ],
            E_CORE_ERROR         => [ true,  'E_CORE_ERROR' ],
            E_CORE_WARNING       => [ true,  'E_CORE_WARNING' ],
            E_COMPILE_ERROR      => [ true,  'E_COMPILE_ERROR' ],
            E_COMPILE_WARNING    => [ true,  'E_COMPILE_WARNING' ],
            E_USER_ERROR         => [ true,  'E_USER_ERROR' ],
            E_USER_WARNING       => [ false, 'E_USER_WARNING' ],
            E_USER_NOTICE        => [ false, 'E_USER_NOTICE' ],
            E_STRICT             => [ false, 'E_STRICT' ],
            E_RECOVERABLE_ERROR  => [ true,  'E_RECOVERABLE_ERROR' ],
            E_DEPRECATED         => [ false, 'E_DEPRECATED' ],
            E_USER_DEPRECATED    => [ false, 'E_USER_DEPRECATED' ],
        ];

        $fatal = true;
        $type = strval($errno);

        if (array_key_exists($errno, $translation))
            [ $fatal, $type ] = $translation[$errno];

        $this->handleError([
            'type'      => $type,

            'message'   => $errstr,
            'location'  => $errfile . ':' . $errline,

            'trace'     => $this->getFilteredTrace(/* $exception= */ null),

        ], /* $fatal = */ $fatal);
    }

    // Called when a PHP exception occurs. Exceptions are always considered to be fatal, given that
    // invocation of this method implies that it hasn't been caught by code elsewhere.
    public function onException($exception) {
        $this->handleError([
            'type'      => 'E_EXCEPTION',

            'message'   => $exception->getMessage(),
            'location'  => $exception->getFile() . ':' . $exception->getLine(),

            'trace'     => $this->getFilteredTrace($exception),

        ], /* $fatal = */ true);
    }

    // Handles an error that occurred, identified by the given |$context|. The |$fatal| argument
    // indicates whether processing should be (forcefully) stopped at the end of this method.
    private function handleError(array $context, bool $fatal): void {
        $this->writeToLog($context);
        $this->distributeAlert($context, $fatal);

        if (!$fatal)
            return;

        if (php_sapi_name() === 'cli') {
            echo PHP_EOL;
            echo 'An unexpected error has occurred, and execution has been terminated:';
            echo PHP_EOL;
            echo json_encode($context);
            echo PHP_EOL;
        } else {
            echo json_encode([
                'error'     => 'An unexpected error has occurred.',
                '_context'  => $context,
            ]);
        }

        exit;
    }

    // Distributes an alert for the given |$context|. Some threshold checking will be done prior to
    // actually sending a message. Care is taken to not cause additional issues in here.
    private function distributeAlert(array $context, bool $fatal): void {
        if (!$fatal)
            return;  // only distribute alerts for fatal issues

        $configuration = Configuration::getInstance();

        $logging = $configuration->get('logging');
        if (!$logging['alerts'])
            return;  // alert functionality has been disabled

        $message = new \Nette\Mail\Message;
        $message->setFrom($logging['sender']);

        foreach ($logging['recipients'] as $recipient)
            $message->addTo($recipient);

        $message->setSubject('Volunteer Portal Exception');

        // Create the message's contents based on the given |$context|.
        ob_start();

        var_dump($context);

        if (array_key_exists('includePost', $logging) && $logging['includePost'])
            var_dump($_POST);

        $messageHeader = 'An exception has occurred on the Volunteer Portal:<br /><br />';
        $messageContents = ob_get_clean();

        $message->setHtmlBody($messageHeader . '<pre>' . $messageContents . '</pre>');

        // TODO: Switch to SMTP?
        $mailer = new \Nette\Mail\SendmailMailer;
        $mailer->send($message);
    }

    // Writes the given |$context| to the error log. All necessary checks will be done to ensure
    // that this method doesn't throw or error, but we're dealing with filesystem, so who knows.
    private function writeToLog(array $context): void {
        if (!is_writable(self::ERROR_LOG))
            return;

        $entry = time() . ' ' . json_encode($context) . PHP_EOL;

        file_put_contents(self::ERROR_LOG, $entry, FILE_APPEND);
    }

    // Generates a backtrace as an array based on the given |$exception|, or the current calling
    // stack when no exception has been given. Internal stack trace inputs may be omitted.
    private function getFilteredTrace(?Error $exception): array {
        $traceInput = $exception ? $exception->getTrace()
                                 : debug_backtrace();

        if ($exception === null && count($traceInput) >= 2) {
            array_shift($traceInput);  // ErrorHandler::onError
            array_shift($traceInput);  // The `location` of the actual error
        }

        $trace = [];

        foreach ($traceInput as $entry) {
            $location = $entry['file'] . ':' . $entry['line'];
            $context = '';

            if (array_key_exists('class', $entry) && $entry['class'] !== '') {
                $context = ' @ ' . $entry['class'] . '::' . $entry['function'] . '()';
            } else if (array_key_exists('function', $entry) && $entry['function'] !== '') {
                $context = ' @ ' . $entry['function'] . '()';
            }

            $trace[] = $location . $context;
        }

        return $trace;
    }
}
