#!/bin/bash
# Start Laravel built-in server with custom PHP limits for large uploads
php -c php-local.ini artisan serve "$@"
