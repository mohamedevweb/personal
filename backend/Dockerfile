# syntax=docker/dockerfile:1
#
# Two build targets share one source tree:
#   --target app  → PHP-FPM, runs the API, the queue worker and the scheduler
#   --target web  → nginx, serves public/ and proxies PHP to the app container
#
# Keeping them separate means nginx never ships a PHP runtime and the app
# container never ships a web server.

# ---------------------------------------------------------------- dependencies
FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
      --no-dev \
      --no-scripts \
      --no-interaction \
      --prefer-dist \
      --optimize-autoloader

# ------------------------------------------------------------------------- app
FROM php:8.4-fpm-alpine AS app

# pdo_pgsql: PostgreSQL driver. pcntl: queue worker signal handling.
# opcache: bytecode cache, sized for production below.
RUN apk add --no-cache postgresql-dev icu-dev \
 && docker-php-ext-configure opcache --enable-opcache \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql pcntl opcache intl \
 && apk del icu-dev \
 && rm -rf /var/cache/apk/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/personal.ini

WORKDIR /var/www/html

# www-data already exists in the base image; the application runs as that user
# rather than root.
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor

RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
      storage/framework/views storage/logs bootstrap/cache \
 && php artisan package:discover --ansi \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER www-data

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ------------------------------------------------------------------------- web
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Only the document root is copied — no PHP source leaves the app image.
COPY --from=app /var/www/html/public /var/www/html/public

EXPOSE 80
