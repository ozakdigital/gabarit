# Environnement de dev de Gabarit : PHP 8.3 CLI + Composer + Node + git + client Docker.
# Le client Docker sert aux tests de génération (`docker compose config`).
FROM php:8.3-cli-alpine

RUN apk add --no-cache git make bash nodejs npm docker-cli docker-cli-compose icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN git config --system user.name "Gabarit" \
    && git config --system user.email "gabarit@localhost" \
    && git config --system --add safe.directory '*'

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app
