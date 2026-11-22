# UNB Wire — app + horizon image (php-fpm, Alpine)

# This file mirrors /opt/unb-wire-ngx/docker/app/Dockerfile on the live server.
# Keep the two in sync: the live Dockerfile is the runtime source of truth,
# this copy makes the build reproducible from the repo (COS-75 follow-up —
# `ftp` was missing here, which broke flysystem-ftp with "FTP_BINARY" undefined
# and left FTP deliveries stuck in a retry loop).

FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    nodejs \
    npm

# Configure GD with JPEG, WebP, and FreeType support (COS-6 WebP pipeline).
# ftp is required by league/flysystem-ftp (PushFtpDelivery / FTP channels).
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    ftp \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    zip \
    opcache

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
