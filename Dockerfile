FROM composer:2.8.12 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative

FROM indexyz/php@sha256:df9fbe5e1140d70f9b4d25f6e0ea0d176b0bb6c01991dd361ba6cffc179002e3

LABEL org.opencontainers.image.source="https://github.com/jameqq/SSPanel-Metron" \
      org.opencontainers.image.description="SSPanel-Metron" \
      org.opencontainers.image.licenses="MIT"

COPY --chown=www-data:www-data . /var/www
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/vendor
COPY docker/sspanel.cron /etc/cron.d/sspanel
WORKDIR /var/www

RUN cp config/.config.example.php config/.config.php && \
    cp config/appprofile.example.php config/appprofile.php && \
    chown -R www-data:www-data storage config && \
    find storage -type d -exec chmod 0750 {} \; && \
    find storage -type f -exec chmod 0640 {} \; && \
    chmod 0644 /etc/cron.d/sspanel && \
    php xcat initQQWry && \
    php xcat ClientDownload && \
    { \
        echo '[program:crond]'; \
        echo 'command=cron -f'; \
        echo 'autostart=true'; \
        echo 'autorestart=true'; \
        echo 'killasgroup=true'; \
        echo 'stopasgroup=true'; \
    } > /etc/supervisor/crond.conf

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl --fail --silent --show-error http://127.0.0.1/ >/dev/null || exit 1
