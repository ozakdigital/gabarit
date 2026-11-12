# Gabarit — commandes de développement (CLAUDE.md §14).
# Tout s'exécute dans le conteneur `php` (PHP 8.3). En CI, où PHP est installé : `make test RUN=`.
export COMPOSE_BAKE := false
RUN ?= docker compose run --rm -T php

.DEFAULT_GOAL := help
.PHONY: help up down install test test-nightly lint lint-ui cs-fix matrix gen doctor sh web web-down

help: ## Affiche cette aide
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  %-13s %s\n", $$1, $$2}'

up: ## Démarre l'environnement de dev (docker compose)
	docker compose up -d --build
	$(RUN) composer install --no-interaction
	$(RUN) composer --working-dir=apps/web/api install --no-interaction

down: ## Arrête l'environnement
	docker compose down

install: ## Installe les dépendances
	$(RUN) composer install --no-interaction
	$(RUN) composer --working-dir=apps/web/api install --no-interaction

test: ## Tests unitaires, CLI, API web et génération d'un échantillon de la matrice
	$(RUN) vendor/bin/phpunit
	$(RUN) sh -c 'cd apps/web/api && APP_ENV=test vendor/bin/phpunit'

test-nightly: ## Toute la matrice (656 générations) + installeurs réels (réseau, lent)
	$(RUN) env GABARIT_FULL_MATRIX=1 vendor/bin/phpunit
	$(RUN) vendor/bin/phpunit --group real-installers

lint: ## PHPStan niveau 8 + PHP-CS-Fixer (vérification) + validation de la matrice
	$(RUN) vendor/bin/phpstan analyse --no-progress --memory-limit=1G
	$(RUN) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(RUN) php apps/cli/bin/gabarit matrix --json > /dev/null

lint-ui: ## Vérifie les types de l'interface web (vue-tsc), dans un conteneur Node
	docker run --rm -v "$(CURDIR)/apps/web/ui:/ui" -v /ui/node_modules -w /ui node:22-alpine sh -c "npm ci --no-audit --no-fund && npx vue-tsc -b"

cs-fix: ## Corrige le style de code
	$(RUN) vendor/bin/php-cs-fixer fix

matrix: ## Affiche la matrice
	$(RUN) php apps/cli/bin/gabarit matrix

gen: ## Génère un projet d'exemple dans ./tmp/demo
	$(RUN) sh -c 'rm -rf tmp/demo && php apps/cli/bin/gabarit new demo --dir=tmp/demo --frontend=vue --services=redis,mailpit --no-interaction'

doctor: ## Vérifie les outils (dans le conteneur)
	$(RUN) php apps/cli/bin/gabarit doctor

sh: ## Shell dans le conteneur PHP
	docker compose run --rm php sh

web: ## Lance le site web en local (http://localhost:8090)
	APP_SECRET=$${APP_SECRET:-gabarit-dev-secret} docker compose -f apps/web/compose.yaml up -d --build
	@echo "→ Site : http://localhost:$${WEB_PORT:-8090}"

web-down: ## Arrête le site web local
	APP_SECRET=x docker compose -f apps/web/compose.yaml down
