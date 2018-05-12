[![Build Status](https://travis-ci.org/AnimeNL/anime-2017.svg?branch=master)](https://travis-ci.org/AnimeNL/anime-2017)
[![NPM Dependencies](https://david-dm.org/AnimeNL/anime-2017.svg)](https://david-dm.org/AnimeNL/anime-2017/)

Anime 2018 Volunteer Portal
===
This repository contains the code powering the Anime 2018 volunteer portals. It contains a limited
amount of backend logic for importing and normalizing data from external parties, as well as the
frontend for both the client-side logic and the portal's visual interface.

While this portal has been written for the [AnimeCon](http://www.animecon.nl/) convention, other
events are most welcome to adopt it for their needs. Pull requests to make this easier are welcomed.

## Development setup (Docker)
You can easily get a development setup on Linux by using [Docker](https://www.docker.com/).
To build the container, run `docker build -t anime2017 docker`.
After that, run one of the following commands:
- To run the services: `./bin/services`
- To run a test webserver: `./bin/serve`
- To run the linter: `./bin/lint`
- To run the testsuite: `./bin/test`

### Accessing development host
By default, there is a base configuration for the "anime.test" environment.
To access this after starting the development setup via Docker, add a line `127.0.0.1  anime.test` to the `/etc/hosts` file.
After having done this, you can access the running development webserver at http://anime.test:8080/ .

## Backend code (PHP)
The backend is located in the [/anime/](/anime/) directory and has been written in PHP.

A large portion of the backend code exists to import data from third-party sources in a rather
pendantic way, given that this has caused issues in the past, for which a Service Manager has been
created in the [/anime/Services/](/anime/Services/) directory.

A number of external dependencies will be pulled in using [Composer](https://getcomposer.org). Be
sure to run `composer install` when starting to work with this repository, and `composer update`
every time you pull new changes.

## Frontend code (JavaScript)
You need to generate anime.js, for which [Gulp](https://github.com/gulpjs/gulp) is used. Install nodejs
and npm, and then run `npm install`. Afterwards, run `npm run-script build` to generate anime.js.

## Installation
Create the following files, and make sure that they're readable by the current user, as well as the
user that will be used for serving the application (e.g. _apache_).

  - anime/Services/error.log
  - anime/Services/state.json
  - configuration/teams/

If you are running on a system that has SELinux set to enforcing, make sure you change the context
of anime/Services/error.log to `httpd_sys_rw_content_t`
(run `chcon -t httpd_sys_rw_content_t anime/Services/error.log`).

**TODO**: Document both backend and frontend deployment in this section.

## Frontend
Now try to reach $yourhost/anime.css. If that gives you a Not Found error, you need to enable htaccess
overrides (set "AllowOverride FileInfo Options" in your apache config for the directory where you are
deploying, or copy all of the .htaccess entries into the apache configuration).

## Configuration
Look in anime/Services/Import{Program,Schedule,Team}Service.php to see how the various services import
the data formats. Then move configuration/configuration.json-example to configuration/configuration.json
and edit the email address and configure the Services you want to use for importing your schedule and
other configuration.

**TODO**: Write some more explanation about importing the configuration.
