AnimeCon 2021 Volunteer Portal (backend)
===
This repository contains a Volunteer Portal backend implementation for the [AnimeCon 2021](https://www.animecon.nl/) festival. The frontend may be found in the [volunteer-portal](https://github.com/AnimeNL/volunteer-portal) project, also published on GitHub.

**This project has been designed specifically for AnimeCon, and will not consider contributions that are not immediately applicable to AnimeCon.**

## API-driven communication
The frontend and backend communicate with each other through a set of [APIs](https://github.com/AnimeNL/volunteer-portal/blob/master/API.md), each of which share [serve.php](api/serve.php) as their entry point, which then delegates to [Api.php](api/anime/Api.php) for actual functionality.
