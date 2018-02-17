#!/bin/sh

if [ "$1" = "prod" ]
then
    cd backend
    chmod 0777 var/cache
    chmod 0777 var/logs
    composer install --no-dev --optimize-autoloader --no-interaction
    composer compile --no-dev --no-interaction

    cd ../web
    npm install

    cd ../frontend
    npm install
    npm run build:prod

else
    cd backend
    composer install
    composer compile:dev

    cd ../web
    npm install

    cd ../frontend
    npm install
    npm run build
fi