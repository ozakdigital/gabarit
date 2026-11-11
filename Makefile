# Gabarit — commandes de développement (CLAUDE.md §14).
# Tout s'exécute dans le conteneur `php` (PHP 8.3). En CI, où PHP est installé : `make test RUN=`.
export COMPOSE_BAKE := false
RUN ?= docker compose run --rm -T php

.DEFAULT_GOAL := help
.PHONY: help up down install test test-nightly lint cs-fix matrix gen doctor sh

help: ## Affiche cette aide
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  %-13s %s\n", $$1, $$2}'

up: ## Démarre l'environnement de dev (docker compose)
	docker compose up -d --build
	$(RUN) composer install --no-interaction

down: ## Arrête l'environnement
	docker compose down

install: ## Installe les dépendances
	$(RUN) composer install --no-interaction

test: ## Tests unitaires, CLI et génération d'un échantillon couvrant de la matrice
	$(RUN) vendor/bin/phpunit

test-nightly: ## Toute la matrice (656 générations) + installeurs réels (réseau, lent)
	$(RUN) env GABARIT_FULL_MATRIX=1 vendor/bin/phpunit
	$(RUN) vendor/bin/phpunit --group real-installers

lint: ## PHPStan niveau 8 + PHP-CS-Fixer (vérification) + validation de la matrice
	$(RUN) vendor/bin/phpstan analyse --no-progress --memory-limit=1G
	$(RUN) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(RUN) php apps/cli/bin/gabarit matrix --json > /dev/null

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
