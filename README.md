AnimeCon 2021 Volunteer Portal (backend)
===
This repository contains a Volunteer Portal backend implementation for the [AnimeCon 2021](https://www.animecon.nl/) festival. The frontend may be found in the [volunteer-portal](https://github.com/AnimeNL/volunteer-portal) project, also published on GitHub.

**This project has been designed specifically for AnimeCon, and will not consider contributions that are not immediately applicable to AnimeCon.**

## API-driven communication
The frontend and backend communicate with each other through a set of [APIs](https://github.com/AnimeNL/volunteer-portal/blob/master/API.md), each of which share [serve.php](api/serve.php) as their entry point, which then delegates to [Api.php](api/anime/Api.php) for actual functionality.

## Installation
In order to run this service, you will need access to a server that supports PHP 8.0.2 or later. After checking out this respository and executing `composer install`, follow these steps:

  1. Grant read/write permissions to the `api/cache/` directory for the webserver.
  1. Create a `api/configuration/configuration.json5` file based on [the example](api/configuration/configuration.example.json5).
  1. Create a `api/configuration/google-credentials.json` file containing [Google authorization credentials](https://github.com/googleapis/google-api-php-client/blob/master/docs/oauth-web.md#create-authorization-credentials).
  1. Run `api/google-auth.php` on the CLI to establish an authorization token.
  1. Set up `api/services/execute.php` to be executed every minute using a cronjob.
