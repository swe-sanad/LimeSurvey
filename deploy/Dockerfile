FROM php:8.1-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
      libpq-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pdo_mysql gd zip intl exif gettext mbstring \
    && a2enmod rewrite headers \
    && apt-get clean

RUN printf 'memory_limit=256M\nupload_max_filesize=64M\npost_max_size=64M\nmax_execution_time=120\ndate.timezone=UTC\n' \
      > /usr/local/etc/php/conf.d/limesurvey.ini

COPY --chown=www-data:www-data src/ /var/www/html/

RUN chown -R www-data:www-data \
      /var/www/html/tmp /var/www/html/upload \
      /var/www/html/application/config /var/www/html/application/logs
