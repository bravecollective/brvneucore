# Installation

<!-- toc -->

- [EVE API Setup](#eve-api-setup)
- [App Setup](#app-setup)
  * [Server Requirements](#server-requirements)
  * [Install/Update](#installupdate)
    + [Pre-built Distribution file](#pre-built-distribution-file)
    + [Git](#git)
  * [Cron Job](#cron-job)
- [Other Installation Methods](#other-installation-methods)
  * [Vagrant](#vagrant)
  * [Docker](#docker)
  * [Deploy on Heroku](#deploy-on-heroku)
  * [Deploy on AWS Beanstalk](#deploy-on-aws-beanstalk)

<!-- tocstop -->

## EVE API Setup

- Visit https://developers.eveonline.com and create a new application.
- Connection Type: "Authentication & API Access", add the required scopes. Scopes for the backend
  are configured with the environment variable NEUCORE_EVE_SCOPES. To use the "auto-allowlist"
  feature for the Watchlist, the scopes must include `esi-corporations.read_corporation_membership.v1`.
- Set the callback to `https://your.domain/login-callback`.

## App Setup

### Server Requirements

A Linux server (others may work, but are not tested).

To run the application:
* PHP >=7.3.0, see `composer.json` in the root directory and `backend/composer.json` for necessary and suggested 
  extensions (APCu highly recommended).
* MariaDB or MySQL Server (currently only tested with MySQL 8.0 and MariaDB 10.2, 10.5).  
  Unit tests can also be run using an SQLite in-memory database, but migration files work with MySQL/MariaDB only.
* Apache or another HTTP Server.
    * Set the document root to the `web` directory.
    * A sample Apache configuration is included in the [Vagrantfile](../Vagrantfile) file and there 
      is a [.htaccess](../web/.htaccess) file in the `web` directory.
    * A sample [Nginx configuration](docker-nginx.conf) file can be found in the `doc` directory.

Additionally, to build the application:
* Composer 1.x.
* Node.js >=10.16.0 <15 with npm >=6.9.0 <7 (only tested with LTS releases v10, 12 and 14).
* Java 8+ runtime to generate the OpenAPI JavaScript client.

### Install/Update

Clone the repository or [download](https://github.com/tkhamez/neucore/releases) the pre-built distribution.

Copy `backend/.env.dist` file to `backend/.env` and adjust values or
set the required environment variables accordingly.

Adjust `web/theme.js` if you want another default theme, or add additional JavaScript code, e.g. for user tracking.

Make sure that the web server can write to the log and cache directories, by default 
`backend/var/logs` and `backend/var/cache`.

Please note that both the web server and console user write the same files to the cache directory,
so make sure they can override each other's files, e.g. by putting them into each other's group
(the app uses umask 0002 when writing files and directories).

If available, the app uses an APCu cache in production mode. This must be cleared during an update:
depending on the setup, restart the web server or php-fpm.

#### Pre-built Distribution file

If you downloaded the .tar.gz file, you only need to run the database migrations and seeds and clear the cache.

If you are using a different cache directory, you must first copy or generate the Doctrine proxy cache files:
```
cp -R backend/var/cache/proxies /path/to/your/cache/proxies
# or
cd backend
vendor/bin/doctrine orm:generate-proxies
```

Then execute (adjust cache path if necessary)
```
cd backend
rm -rf var/cache/di
vendor/bin/doctrine-migrations migrations:migrate --no-interaction
bin/console doctrine-fixtures-load
```

#### Git

If you have cloned the repository, you must install the dependencies and build the backend and frontend:
```
./install.sh
# or
./install.sh prod
```

### Cron Job

Set up necessary cron jobs, e.g. update characters every 2 hours and the rest 3 times daily 
using a lock file (adjust user and paths):

```
0 0,2,6,8,10,14,16,18,22 * * * neucore /var/www/neucore/backend/bin/run-jobs2.sh
0 4,12,20 * * * neucore /usr/bin/flock -n /tmp/neucore-run-jobs.lock /var/www/neucore/backend/bin/run-jobs.sh
```

The output is logged to backend/var/logs.

## Other Installation Methods

### Vagrant

See [Vagrantfile](../Vagrantfile) for an outdated example.

### Docker

Only tested on Linux and once on macOS.

Copy `backend/.env.dist` file to `backend/.env` and adjust values, the database password and user are both `neucore`,
the database host is `db`.

- Build the containers with `export UID && docker-compose build`
- Start services: `export UID && docker-compose up`
- Install the app: `export UID && ./install-docker.sh`
- Run tests and other commands in the php-fpm and node containers:  
    `export UID && docker-compose exec php-fpm /bin/sh`  
    `export UID && docker-compose run node /bin/sh`

The web application is available at http://localhost:8080. The database is also available at `127.0.0.1:30306`, 
the data is stored in the `.db` subdirectory.

### Deploy on Heroku

You can deploy the application on a free [Heroku](https://www.heroku.com) account.

- Create a new app
- Add a compatible database, e. g. JawsDB Maria.
- Add the necessary config vars (see `backend/.env.dist` file) and set the following:
  - NEUCORE_LOG_PATH=php://stderr
- Add build packs in this order:

```
heroku buildpacks:add heroku/java
heroku buildpacks:add heroku/nodejs
heroku buildpacks:add heroku/php
```

### Deploy on AWS Beanstalk

NOTE: The configuration in `.ebextensions` is for a box with PHP 7.2, it's outdated as Neucore requires PHP 7.3 now.

A current configuration for Amazon Linux 2 is available at 
[bravecollective/neucore-beanstalk](https://github.com/bravecollective/neucore-beanstalk).

- Add an IAM user with Policy "AWSElasticBeanstalkFullAccess"
- Create a database (RDS)
- Create app environment:
    ```
    eb init -i
    eb create neucore-dev
    ```
- Add a security group for the database that includes the new environment
- Add a database for Neucore
- Add environment Variables (NEUCORE_APP_ENV, NEUCORE_DATABASE_URL etc.)
- Deploy again: `eb deploy`
