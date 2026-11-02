# syntax=docker/dockerfile:1
# RestaurantApp — PHP-FPM 8.5 (Debian-basis) met de PDO-MariaDB/MySQL-driver.
# Debian (niet alpine) wordt gebruikt omdat pdo_mysql er zonder extra systeempackages
# uit de meegeleverde (static-linked) libmysqlclient wordt gebouwd.
FROM php:8.5-fpm

# Composer in de container (handig voor `docker compose exec -T php composer ...`)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# pdo_mysql: de PDO-driver die Doctrine DBAL/ORM met MariaDB gebruikt.
# PHP 8.x bouwt dit zonder externe systeemdependencies.
RUN docker-php-ext-install pdo_mysql

# FPM luistert op alle interfaces (niet alleen 127.0.0.1), zodat de nginx-container
# via het compose-netwerk naar 'php:9000' kan connecteren.
COPY docker/php/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www
EXPOSE 9000

CMD ["php-fpm"]
