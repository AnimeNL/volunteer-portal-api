<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

// This script compiles `main.js` on the fly using Babelify with the ES2016 preset. It certainly is
// not a fast operation, so this should only be used for development purposes.

$beginTime = microtime(true);
$compiled = shell_exec('../node_modules/.bin/browserify main.js -t babelify');
$totalTime = microtime(true) - $beginTime;

echo '/** compile time: ' . sprintf('%.2f', $totalTime * 1000) . 'ms **/' . PHP_EOL;
echo $compiled;
