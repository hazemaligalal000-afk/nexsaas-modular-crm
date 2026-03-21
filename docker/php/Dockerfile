FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        mbstring \
        intl \
        gd \
        opcache \
        bcmath \
        pcntl \
        sockets

# Redis extension
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY docker/php/php.ini /usr/local/etc/php/conf.d/nexsaas.ini

WORKDIR /var/www/html

RUN addgroup -g 1000 nexsaas && adduser -u 1000 -G nexsaas -s /bin/sh -D nexsaas
USER nexsaas
