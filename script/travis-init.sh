#!/bin/bash

pyrus install pecl/redis && pyrus build pecl/redis
echo "extension=redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo '{"config":{"github-oauth": {"github.com":"fa354b2f030836334eac842f8fd69a03e353d247"}}}' > ~/.composer/config.json
composer self-update
composer install --dev --no-progress --no-custom-installers --no-scripts
