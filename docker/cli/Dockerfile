# Image de distribution de la CLI Gabarit : le code et ses dépendances sont figés dedans.
# Construite par les installeurs (install.sh, install.ps1) et par `gabarit self-update`.
#   docker build -f docker/cli/Dockerfile -t gabarit-cli .
FROM php:8.3-cli-alpine

# git, composer et node servent aux installeurs officiels (composer create-project, create-vite, ng new).
RUN apk add --no-cache git nodejs npm icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Le lanceur peut exécuter le conteneur sous l'UID de l'utilisateur (Linux, macOS) :
# HOME et les caches doivent donc être inscriptibles par n'importe qui.
ENV HOME=/tmp/home \
    COMPOSER_HOME=/tmp/home/.composer \
    COMPOSER_ALLOW_SUPERUSER=1 \
    npm_config_update_notifier=false \
    GABARIT_IN_CONTAINER=1
RUN mkdir -p /tmp/home && chmod 1777 /tmp/home \
    && git config --system user.name "Gabarit" \
    && git config --system user.email "gabarit@localhost" \
    && git config --system --add safe.directory '*' \
    && git config --system init.defaultBranch main

WORKDIR /opt/gabarit
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-autoloader --no-scripts
COPY config ./config
COPY packages/core/src ./packages/core/src
COPY packages/core/templates ./packages/core/templates
COPY apps/cli/src ./apps/cli/src
COPY apps/cli/bin ./apps/cli/bin
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

WORKDIR /work
ENTRYPOINT ["php", "/opt/gabarit/apps/cli/bin/gabarit"]
