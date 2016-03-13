# Service Manager
The service manager powers periodic tasks that are necessary in order to keep the information
displayed in the volunteer portal up-to-date.

While each of these tasks could be an independent cron job, it makes sense to unify them under a
small framework that provides the appropriate scheduling, logging and alerting infrastructure. The
service manager is deliberately straightforward and basic in functionality.

## What is a service?
A [Service](/anime/Services/Service.php) is a routine that has to be executed periodically
regardless of whether the volunteer portal has been visited by someone.

It has a unique `identifier` and defines its `frequency` in minutes. It also defines the `execute()`
routine that is to be executed at the given frequency.

## What if something goes wrong?
When a service either fails or throws an exception, a message will be written to a [ServiceLog]
(/anime/Services/ServiceLog.php) instance. The [default log](/anime/Services/ServiceLogImpl.php)
will write such results to a file, and inform a given set of people about the failure.

## What are some examples of services?
The following list contains the services implemented under this framework:

- None yet. That's up next.
