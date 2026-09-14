# syntax=docker/dockerfile:1.7
# Builds the application images from one asset build: php-fpm for development and for production.
# Static files are served by the `nginx` service of docker-compose from the same public/ directory.
ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pcntl intl zip bcmath opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Development image: sources are bind-mounted; the runtime user matches the host user
# so files created in storage/ stay writable on both sides.
FROM base AS dev

ARG UID=1000
ARG GID=1000
RUN apk add --no-cache shadow \
    && groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g www-data www-data

COPY docker/php/conf.d/app.dev.ini /usr/local/etc/php/conf.d/zz-app.ini

USER www-data

# The SPA is compiled once and reused by both the application image and the web image.
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js tsconfig.json ./
COPY resources ./resources
RUN npm run build

# Production image: everything is baked in, nothing is mounted.
FROM base AS prod

ENV APP_ENV=production

# Dependencies first: they change far less often than the code, so this layer stays cached.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY --chown=www-data:www-data . .
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY docker/php/conf.d/app.prod.ini /usr/local/etc/php/conf.d/zz-app.ini

# Configuration is cached at deploy time, not here: this image knows nothing about the environment it runs in.
# The writable directories are recreated explicitly: .dockerignore keeps their contents out of the build context,
# and Laravel refuses to boot without them.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
