FROM php:8.1-apache

# Устанавливаем свежий restic (в Debian bookworm 0.14 — нет stats --json, summary)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    bzip2 \
    && rm -rf /var/lib/apt/lists/* \
    && curl -L https://github.com/restic/restic/releases/download/v0.19.1/restic_0.19.1_linux_amd64.bz2 \
        | bzip2 -d > /usr/local/bin/restic \
    && chmod +x /usr/local/bin/restic

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
