#!/bin/bash -xe
composer install
composer update
npm install
npm run-script build

if [ "x$@" == "xlint" ];
then
    composer lint
elif [ "x$@" == "xtest" ];
then
    composer test
elif [ "x$@" == "xservices" ];
then
    php anime/Services/execute.php
elif [ "x$@" == "xserve" ];
then
    httpd -X -f /usr/local/etc/httpd.conf
else
    exec /bin/bash
fi
