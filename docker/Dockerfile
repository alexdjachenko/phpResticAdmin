FROM php:8.1-apache

RUN apt-get update && apt-get install -y --no-install-recommends restic && rm -rf /var/lib/apt/lists/*

COPY htdocs/ /var/www/html/
COPY src/ /var/www/src/
COPY vendor/ /var/www/vendor/
COPY templates/ /var/www/templates/
COPY data/ /var/www/data/
COPY composer.json /var/www/

RUN a2enmod rewrite
COPY docker/apache-config.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www
