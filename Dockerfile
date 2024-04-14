FROM php:8.3-apache

WORKDIR /var/www/html/

RUN pecl install xdebug \
    && apt update \
    && apt install libzip-dev -y \
    && docker-php-ext-enable xdebug \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

RUN echo "xdebug.mode=develop,debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

#COPY . .
#COPY src src
#COPY controller controller
#COPY views views
#COPY clean.php .

EXPOSE 80