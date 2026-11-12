# Gabarit — commandes de développement (CLAUDE.md §14).
# Tout s'exécute dans le conteneur `php` (PHP 8.3, Composer, Node). Dans ce conteneur (Dev Container,
# `make sh`) ou en CI, les commandes tournent directement : RUN est vide.
# Sans make (Windows) : bin/dev <commande>, ou bin\dev.cmd <commande>.
export COMPOSE_BAKE := false
RUN ?= $(if $(GABARIT_DEV),,docker compose run --rm -T php)

# Options de `make gen`.
FW ?= symfony
DB ?= postgresql
FRONT ?= vue
SERVICES ?= redis,mailpit
PHP ?=

.DEFAULT_GOAL := help
.PHONY: help up down install test test-nightly lint lint-ui test-ui test-e2e cs-fix matrix gen doctor sh web web-down web-dev web-dev-down hooks

help: ## Affiche cette aide
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  %-13s %s\n", $$1, $$2}'

up: ## Démarre l'environnement de dev (docker compose) et installe les dépendances
	docker compose up -d --build php
	$(MAKE) install

down: ## Arrête l'environnement
	docker compose --profile web-dev down

install: ## Installe les dépendances (Composer, npm)
	$(RUN) composer install --no-interaction
	$(RUN) composer --working-dir=apps/web/api install --no-interaction
	$(RUN) sh -c 'cd apps/web/ui && npm install --no-audit --no-fund'

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

lint-ui: ## Vérifie les types de l'interface web (vue-tsc)
	$(RUN) sh -c 'cd apps/web/ui && npm install --no-audit --no-fund && npx vue-tsc -b'

test-ui: ## Tests de composants de l'interface web (Vitest)
	$(RUN) sh -c 'cd apps/web/ui && npm install --no-audit --no-fund && npm test'

test-e2e: ## Parcours complet sur le site lancé par `make web` (Playwright, depuis la machine)
	docker run --rm --add-host=host.docker.internal:host-gateway -e E2E_URL=http://host.docker.internal:$${WEB_PORT:-8090} \
		-v "$(CURDIR)/apps/web/ui:/ui" -v /ui/node_modules -w /ui mcr.microsoft.com/playwright:v1.65.0-noble \
		sh -c "npm ci --no-audit --no-fund && npx playwright test"

cs-fix: ## Corrige le style de code
	$(RUN) vendor/bin/php-cs-fixer fix

matrix: ## Affiche la matrice
	$(RUN) php apps/cli/bin/gabarit matrix

gen: ## Génère un projet dans ./tmp/demo (FW=, DB=, FRONT=, PHP=, SERVICES=)
	$(RUN) sh -c 'rm -rf tmp/demo && php apps/cli/bin/gabarit new demo --dir=tmp/demo --framework=$(FW) --db=$(DB) --frontend=$(FRONT) --services=$(SERVICES) $(if $(PHP),--php=$(PHP)) --no-interaction'

doctor: ## Vérifie les outils (dans le conteneur)
	$(RUN) php apps/cli/bin/gabarit doctor

sh: ## Shell dans le conteneur PHP
	docker compose run --rm php sh

web: ## Lance le site web en local, comme en production (http://localhost:8090)
	docker build -q -t gabarit-web-api --target api -f apps/web/Dockerfile . > /dev/null
	docker build -q -t gabarit-web-web --target web -f apps/web/Dockerfile . > /dev/null
	APP_SECRET=$${APP_SECRET:-gabarit-dev-secret} docker compose -f apps/web/compose.yaml up -d
	@echo "→ Site : http://localhost:$${WEB_PORT:-8090}"

web-down: ## Arrête le site web local
	APP_SECRET=x docker compose -f apps/web/compose.yaml down

ifdef GABARIT_DEV
web-dev: ## Site en mode développement, rechargé à chaque sauvegarde (http://localhost:5173)
	cd apps/web/ui && npm install --no-audit --no-fund
	sh -c 'php -S 0.0.0.0:8000 -t apps/web/api/public apps/web/api/public/index.php & trap "kill $$!" EXIT; cd apps/web/ui && API_URL=http://localhost:8000 npm run dev -- --host 0.0.0.0'
else
web-dev: ## Site en mode développement, rechargé à chaque sauvegarde (http://localhost:5173)
	docker compose build php
	docker compose --profile web-dev up -d api-dev ui-dev
	@echo "→ Interface : http://localhost:$${UI_DEV_PORT:-5173}   (journaux : docker compose logs -f ui-dev)"
endif

web-dev-down: ## Arrête le site en mode développement
	docker compose --profile web-dev rm -sf api-dev ui-dev

hooks: ## Active les vérifications avant chaque commit (.githooks/pre-commit)
	git config core.hooksPath .githooks
	@echo "→ Vérifications avant commit activées (contourner une fois : git commit --no-verify)"
