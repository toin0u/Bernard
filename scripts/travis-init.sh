#!/bin/bash

wget http://pecl.php.net/get/redis-2.2.4.tgz
tar -xzf redis-2.2.4.tgz
sh -c "cd redis-2.2.4 && phpize && ./configure && sudo make install"
echo "extension=redis.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
echo '{"config":{"github-oauth": {"github.com":"fa354b2f030836334eac842f8fd69a03e353d247"}}}' > ~/.composer/config.json
composer self-update
composer install --dev --no-progress --no-custom-installers --no-scripts
