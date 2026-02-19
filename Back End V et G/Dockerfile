FROM php:8.2-apache

RUN apt-get update && apt-get install -y git unzip libpq-dev libzip-dev \
  && docker-php-ext-install pdo pdo_pgsql zip \
  && a2enmod rewrite \
  && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

ENV APP_ENV=prod
RUN php bin/console cache:clear || true
