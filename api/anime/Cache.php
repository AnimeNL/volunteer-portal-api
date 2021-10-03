<?php
// Copyright 2021 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

declare(strict_types=1);

namespace Anime;

use Cache\Adapter\Filesystem\FilesystemCachePool;

// PSR-6 compatible filesystem-based cache implementation used in the volunteer portal. Implemented
// as a singleton in the application, using the cache/filesystem-adapter as a backend.
//
// @see https://www.php-fig.org/psr/psr-6/
class Cache extends FilesystemCachePool {
    // Path to the cache directory on the filesystem.
    public const CACHE_PATH = __DIR__ . '/../cache';

    private static $instance;

    public static function getInstance(): Cache {
        if (self::$instance === null) {
            $adapter = new \League\Flysystem\Adapter\Local(self::CACHE_PATH);
            $filesystem = new \League\Flysystem\Filesystem($adapter);

            self::$instance = new static($filesystem);
        }

        return self::$instance;
    }
}
