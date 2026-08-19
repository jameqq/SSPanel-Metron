FROM composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative --ignore-platform-req=ext-gd

FROM php:8.4-fpm-bookworm@sha256:c5fb7a0c02f4efe280691910c8b734995fa83598cdcf3115ef5dcb2e4617681c

LABEL org.opencontainers.image.source="https://github.com/jameqq/SSPanel-Metron" \
      org.opencontainers.image.description="SSPanel-Metron" \
      org.opencontainers.image.licenses="MIT"

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        curl libfreetype6-dev libjpeg62-turbo-dev libonig-dev libpng-dev libzip-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j2 bcmath gd mysqli opcache pdo_mysql zip && \
    rm -rf /var/lib/apt/lists/*

COPY --chown=www-data:www-data . /var/www
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/vendor
WORKDIR /var/www

RUN cp config/.config.example.php config/.config.php && \
    cp config/appprofile.example.php config/appprofile.php && \
    chown -R www-data:www-data storage config && \
    find storage -type d -exec chmod 0750 {} \; && \
    find storage -type f -exec chmod 0640 {} \;

USER www-data
EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD php-fpm -t || exit 1

CMD ["php-fpm", "-F"]
