<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

// The Environment class represents the context for the application's data sources, for example to
// allow split data sources based on the hostname.
class Environment {
    // Directory in which static environment content is located.
    private const CONTENT_DIRECTORY = __DIR__ . '/../content/';

    private bool $valid;
    private string $hostname;

    private string $contactName;
    private string $contactTarget;
    private array $events;
    private string $title;

    // Constructor for the Environment class. The |$valid| boolean must be set, and, when set to
    // true, the |$settings| array must be given with all intended options.
    public function __construct(
            bool $valid, string $hostname = 'unknown', array $events = [], array $settings = []) {
        $this->valid = $valid;
        if (!$valid)
            return;

        $this->contactName = $settings['contactName'];
        $this->contactTarget = $settings['contactTarget'];
        $this->events = $events;
        $this->hostname = $hostname;
        $this->title = $settings['title'];
    }

    // Returns whether this Environment instance represents a valid environment.
    public function isValid(): bool {
        return $this->valid;
    }

    // Returns the name of the person who can be contacted for questions.
    public function getContactName(): string {
        return $this->contactName;
    }

    // Returns the link target of the person who can be contacted for questions.
    public function getContactTarget(): string {
        return $this->contactTarget;
    }

    // Recursively loads all static content that has been made available for the current environment
    // from the filesystem. This is a fairly heavy operation, whose result should be cached.
    public function getContent(): array {
        $directoryPath = self::CONTENT_DIRECTORY . $this->getHostname();
        $content = [];

        if (file_exists($directoryPath)) {
            $directoryIterator = new \RecursiveDirectoryIterator($directoryPath);
            $iterator = new \RecursiveIteratorIterator($directoryIterator);

            foreach ($iterator as $file) {
                if ($file->isDir())
                    continue;  // iterators will be visited recursively

                $absolutePath = $file->getPathname();
                if (!str_ends_with($absolutePath, '.html') && !str_ends_with($absolutePath, '.md'))
                    continue;  // only consider HTML and Markdown files for now.

                $relativePath = str_replace($directoryPath, '', $absolutePath);
                $normalizedPath = preg_replace('/\.(html|md)$/', '.html', $relativePath);
                $filteredPath = str_replace('/index.html', '/', $normalizedPath);

                $content[] = [
                    'pathname'  => $filteredPath,
                    'content'   => file_get_contents($absolutePath),
                    'modified'  => $file->getMTime(),
                ];
            }
        }

        return $content;
    }

    // Returns an array with all the Event instances known to this environment.
    public function getEvents(): array {
        return $this->events;
    }

    // Returns the hostname that this Environment instance represents.
    public function getHostname(): string {
        return $this->hostname;
    }

    // Returns the name of the Volunteer Portal instance, e.g. Volunteer Portal.
    public function getTitle(): string {
        return $this->title;
    }
}
