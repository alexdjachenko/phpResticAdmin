FROM php:8.1-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    restic \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json /var/www/

RUN cd /var/www && composer install --no-dev --no-progress

COPY htdocs/ /var/www/html/
COPY src/ /var/www/src/
COPY templates/ /var/www/templates/
COPY data/ /var/www/data/

# restic cache dir — создаём на этапе сборки с правами www-data
RUN mkdir -p /tmp/phpresticadmin/restic-cache \
    && chown -R www-data:www-data /tmp/phpresticadmin

RUN a2enmod rewrite
COPY docker/apache-config.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www
