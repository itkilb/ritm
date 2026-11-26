FROM php:8.2-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        libsqlite3-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        pdo_sqlite \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer


FROM php-base AS vendor

WORKDIR /app

# The full backend is copied because Apiato's merge plugin reads the
# composer.json of every container during dependency resolution.
COPY backend/ ./

ARG INSTALL_DEV_DEPS=true

RUN if [ "$INSTALL_DEV_DEPS" = "true" ]; then \
        composer install --no-interaction --prefer-dist --no-scripts --optimize-autoloader; \
    else \
        composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader; \
    fi


FROM php-base AS runtime

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-ritm.ini
COPY docker/php/zz-ritm.conf /usr/local/etc/php-fpm.d/zz-ritm.conf
COPY docker/php/entrypoint.sh /usr/local/bin/ritm-entrypoint
COPY docker/php/wait-for-db.php /usr/local/bin/wait-for-db.php
RUN chmod +x /usr/local/bin/ritm-entrypoint

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
# Apiato's composer installer places vendor containers (e.g. Documentation)
# under app/Containers/Vendor; copy them from the vendor stage explicitly.
COPY --from=vendor /app/app/Containers/Vendor ./app/Containers/Vendor
COPY backend/ ./

RUN rm -rf \
        bootstrap/cache/*.php \
        storage/framework/cache/* \
        storage/framework/sessions/* \
        storage/framework/views/* \
        storage/logs/* \
    && mkdir -p \
        bootstrap/cache \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage

ENTRYPOINT ["ritm-entrypoint"]
CMD ["php-fpm"]
