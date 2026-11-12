# Site web de Gabarit : deux images construites depuis la racine du dépôt.
#   docker compose -f apps/web/compose.yaml up -d --build
#
#   api : PHP-FPM + API Symfony + cœur de Gabarit (aucune base, aucun stockage)
#   web : nginx + interface Vue compilée, qui relaie /api vers l'API

# --- Interface Vue ---------------------------------------------------------
FROM node:22-alpine AS ui
WORKDIR /ui
COPY apps/web/ui/package.json apps/web/ui/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY apps/web/ui/ ./
RUN npm run build

# --- API Symfony -----------------------------------------------------------
FROM php:8.3-fpm-alpine AS api
RUN apk add --no-cache icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl zip opcache \
    && apk del .build-deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod APP_DEBUG=0
WORKDIR /srv/gabarit
# Même arborescence que le dépôt : l'API trouve le cœur et config/matrix.yaml par chemins relatifs.
COPY config ./config
COPY packages/core/composer.json ./packages/core/composer.json
COPY packages/core/src ./packages/core/src
COPY packages/core/templates ./packages/core/templates
COPY apps/web/api ./apps/web/api
WORKDIR /srv/gabarit/apps/web/api
RUN composer install --no-dev --no-interaction --no-progress --classmap-authoritative \
    && php bin/console cache:warmup \
    && chown -R www-data:www-data var

# --- nginx -----------------------------------------------------------------
FROM nginx:1.28-alpine AS web
COPY apps/web/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=ui /ui/dist /usr/share/nginx/html
