Anime 2016 Volunteer Portal
===
This repository contains the code powering the Anime 2016 volunteer portals. It contains a limited
amount of backend logic for importing and normalizing data from external parties, as well as the
frontend for both the client-side logic and the portal's visual interface.

While this portal has been written for the [AnimeCon](http://www.animecon.nl/) convention, other
events are most welcome to adopt it for their needs. Pull requests to make this easier are welcomed.

## Backend code (PHP)
The backend is located in the [/anime/](/anime/) directory and has been written in PHP.

A large portion of the backend code exists to import data from third-party sources in a rather
pedantic way, given that this has caused issues in the past, for which a Service Manager has been
created in the [/anime/Services/](/anime/Services/) directory.

A number of external dependencies will be pulled in using [Composer](https://getcomposer.org). Be
sure to run `composer install` when starting to work with this repository, and `composer update`
every time you pull new changes.

## Frontend code (JavaScript)
This stuff doesn't exist yet.

## Installation
Create the following files, and make sure that they're readable by the current user, as well as the
user that will be used for serving the application (e.g. _apache_).

  - anime/Services/error.log
  - anime/Services/state.json
  - configuration/teams/

**TODO**: Create a `post-install-cmd` hook for `composer` that creates these files.
**TODO**: Document both backend and frontend deployment in this section.
