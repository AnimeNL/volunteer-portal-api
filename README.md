AnimeCon 2021 Volunteer Portal (backend)
===
This repository contains a Volunteer Portal backend implementation for the [AnimeCon 2021](https://www.animecon.nl/) festival. The frontend may be found in the [volunteer-portal](https://github.com/AnimeNL/volunteer-portal) project, also published on GitHub.

**This project has been designed specifically for AnimeCon, and will not consider contributions that are not immediately applicable to AnimeCon.**

## API-driven communication
The frontend and backend communicate with each other through a set of [documented interfaces](https://github.com/AnimeNL/volunteer-portal/blob/master/API.md), which we provide an implementation for in the [/api/](api/) directory, with the following mapping:

| Request            | Handler |
| :---               | :---    |
| `/api/auth`        | [/api/auth.php](api/auth.php) |
| `/api/content`     | [/api/content.php](api/content.php) |
| `/api/environment` | [/api/environment.php](api/environment.php) |
| `/api/user`        | [/api/user.php](api/user.php) |


# TODO: Clean up the following

## Backend code (PHP)
The backend is located in the [/anime/](/anime/) directory and has been written in PHP.

A large portion of the backend code exists to import data from third-party sources in a rather
pendantic way, given that this has caused issues in the past, for which a Service Manager has been
created in the [/anime/Services/](/anime/Services/) directory.

A number of external dependencies will be pulled in using [Composer](https://getcomposer.org). Be
sure to run `composer install` when starting to work with this repository, and `composer update`
every time you pull new changes.

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

## Configuration
Look in anime/Services/Import{Program,Schedule,Team}Service.php to see how the various services import
the data formats. Then move configuration/configuration.json-example to configuration/configuration.json
and edit the email address and configure the Services you want to use for importing your schedule and
other configuration.

**TODO**: Write some more explanation about importing the configuration.
