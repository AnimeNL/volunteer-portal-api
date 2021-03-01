<?php
// Copyright 2019 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

error_reporting((E_ALL | E_STRICT) & ~E_WARNING);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/error.php';

Header('Access-Control-Allow-Origin: *');
Header('Content-Type: application/json');

$environment = \Anime\Environment::createForHostname($_SERVER['HTTP_HOST']);
if (!$environment->isValid())
    dieWithError('Unrecognized volunteer portal environment.');

$pages = [];

$directoryPath = __DIR__ . '/content/' . $environment->getHostname();
if (file_exists($directoryPath)) {
    $directoryIterator = new RecursiveDirectoryIterator($directoryPath);
    $iterator = new RecursiveIteratorIterator($directoryIterator);

    foreach ($iterator as $file) {
        if ($file->isDir())
            continue;  // iterators will be visited recursively

        $absolutePath = $file->getPathname();
        if (!str_ends_with($absolutePath, '.html') && !str_ends_with($absolutePath, '.md'))
            continue;  // only consider HTML and Markdown files for now.

        $relativePath = str_replace($directoryPath, '', $absolutePath);
        $normalizedPath = preg_replace('/\.(html|md)$/', '.html', $relativePath);
        $filteredPath = str_replace('/index.html', '/', $normalizedPath);

        $pages[] = [
            'pathname'  => $filteredPath,
            'content'   => file_get_contents($absolutePath),
            'modified'  => $file->getMTime(),
        ];
    }
}

echo json_encode([ 'pages' => $pages ]);
