# Gabarit

[Français](README.md) · **English**

A **white-label** PHP application skeleton generator. You pick a framework, a PHP version, a
database, a frontend and services; Gabarit produces a complete, dockerized, branded project that
starts with a single command: `make up`.

The project's architecture rules are in [CLAUDE.md](CLAUDE.md) (in French).

---

## Contents

1. [What Gabarit can generate](#1-what-gabarit-can-generate)
2. [Installing Gabarit](#2-installing-gabarit)
3. [Generating a project](#3-generating-a-project)
4. [What happens during generation](#4-what-happens-during-generation)
5. [The generated project](#5-the-generated-project)
6. [Starting the generated project](#6-starting-the-generated-project)
7. [Developing Gabarit](#7-developing-gabarit)
8. [Extending Gabarit](#8-extending-gabarit)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. What Gabarit can generate

Every available combination is listed in [config/matrix.yaml](config/matrix.yaml), the **single
source of truth**: a combination missing from that file is rejected.

| Framework | Version | PHP | Server-rendered frontend | Branded namespace |
|---|---|---|---|---|
| Symfony | 7.4 LTS | 8.2 – 8.4 | Twig | yes |
| Laravel | 13 | 8.3 – 8.4 | Blade | yes |
| CodeIgniter | 4.7 | 8.2 – 8.4 | — | no (the framework requires `App`) |
| Slim | 4 | 8.2 – 8.4 | — | yes |

- **Databases** (all frameworks): PostgreSQL 17, MySQL 8.4, MariaDB 11.8, SQLite (a plain file in
  the project, no container).
- **Frontends** (all frameworks):
  - server-rendered: Twig (Symfony), Blade (Laravel), or **none** (API only);
  - separate application (SPA) in `frontend/`: Vue 3, React 19, Svelte 5, SolidJS, Preact
    (created by create-vite 9.3.4) and Angular 22 (created by Angular CLI 22.2.6).
- **Extra services**: Redis, Mailpit (catches emails during development).
- **Brand**: display name, PHP namespace, colors, logo (PNG or SVG).

To see the current matrix: `make matrix`.

---

## 2. Installing Gabarit

### Requirements

| Tool | Why |
|---|---|
| **Docker** with Docker Compose v2 | Gabarit runs in a container, and so do the generated projects |
| **make** (recommended) | command shortcuts. On Windows: `choco install make` |
| **git** | to get Gabarit |

**You don't need PHP, Composer or Node on your machine**: Gabarit's container ships PHP 8.3,
Composer, Node 24, git and the Docker client.

### Installation

```bash
git clone <repository-url> gabarit
cd gabarit
make up      # builds Gabarit's image and installs its dependencies (a few minutes the first time)
make doctor  # checks that composer, git, node, npm and docker are available in the container
```

`make up` runs `docker compose up -d --build`, then `composer install` in the `php` container.

> **Without make**: every target in the [Makefile](Makefile) is a plain Docker command, for example
> `docker compose run --rm -T php composer install`. In Git Bash (Windows), prefix commands with
> `MSYS_NO_PATHCONV=1` to stop automatic path conversion, and export `COMPOSE_BAKE=false` if the
> build fails with an "additional privileges requested" message.

---

## 3. Generating a project

The CLI is called `gabarit`. It runs inside the container:

```bash
docker compose run --rm php php apps/cli/bin/gabarit new my-app
```

Gabarit's folder is mounted in the container as `/app`: **the project is created in `./my-app`,
next to Gabarit's code**, unless you pick another folder with `--dir` (see
[Generating elsewhere](#generating-in-another-folder)).

### Interactive mode (default)

Gabarit asks for whatever is missing, with menus built **from the matrix**: only the options
compatible with your previous answers are offered. Prompts are in French.

```
Framework            › Symfony / Laravel / CodeIgniter / Slim
PHP version          › 8.3 / 8.4 …          (only compatible versions are shown)
Database             › PostgreSQL / MySQL / MariaDB / SQLite
Frontend             › Twig / None / Vue 3 / React …
Extra services       › Redis, Mailpit       (checkboxes)
Display name         › My App
PHP namespace        › App                  (not asked for CodeIgniter)
```

A question is skipped when there is only one possible answer (for example the Laravel version).

### Non-interactive mode (scripts, CI)

Every answer is passed as an option:

```bash
docker compose run --rm php php apps/cli/bin/gabarit new shop \
    --framework=laravel --fw-version=13 --php=8.3 \
    --db=mariadb --frontend=blade --services=redis,mailpit \
    --brand-name="Demo Shop" --namespace='Acme\Shop' \
    --primary-color="#e11d48" --no-interaction
```

| Option | Values | Default without interaction |
|---|---|---|
| `<name>` (argument) | slug `[a-z0-9-]`, 2 to 40 characters | required |
| `--framework` | `symfony`, `laravel`, `codeigniter`, `slim` | `symfony` |
| `--fw-version` | from the matrix (`7.4`, `13`, `4.7`, `4`) | the matrix version |
| `--php` | depends on the framework | `8.3` |
| `--db` | `postgresql`, `mysql`, `mariadb`, `sqlite` | `postgresql` |
| `--frontend` | `twig`, `blade`, `none`, `vue`, `react`, `svelte`, `angular`, `solid`, `preact` | the framework's first frontend |
| `--services` | `redis,mailpit` or `none` | none |
| `--brand-name` | letters, digits, spaces, `. , ' & -` | derived from the name (`my-app` → `My App`) |
| `--namespace` | `Acme` or `Acme\Shop` (PascalCase) | `App` |
| `--primary-color`, `--secondary-color` | `#rrggbb` | `#4f46e5`, `#0f172a` |
| `--logo` | path to a PNG or SVG (1 MB max, SVG without scripts) | none |
| `--dir` | target folder (must be empty or missing) | `./<name>` |
| `--no-git` | don't create a git repository | git enabled |
| `--json` | machine-readable output (implies `--no-interaction`) | — |

**Exit codes**: `0` success, `1` invalid choices (nothing was created), `2` error during
generation (installer failure, non-empty folder…).

An incompatible combination is rejected **before** anything is installed, with an explicit message
(messages are in French):

```
[ERROR] Choix invalides.
        phpVersion : PHP 8.2 n'est pas compatible avec Laravel 13 (compatibles : 8.3, 8.4).
```

### Generating in another folder

The container only sees Gabarit's folder. To generate elsewhere, mount the folder you want:

```bash
docker compose run --rm -v "$HOME/projects:/projects" php \
    php apps/cli/bin/gabarit new my-app --dir=/projects/my-app
```

### Other commands

```bash
docker compose run --rm php php apps/cli/bin/gabarit matrix          # available combinations
docker compose run --rm php php apps/cli/bin/gabarit matrix --json   # same, for a machine
docker compose run --rm php php apps/cli/bin/gabarit doctor          # available tools
make gen                                                             # sample project in ./tmp/demo
```

Add `-v` to `new` to see composer and npm output live.

---

## 4. What happens during generation

Gabarit **doesn't ship any framework code**. It calls the official installer at the matrix version,
then adds its own layers. Generation always goes through these 10 steps, shown on screen
(`[3/10] Installation du front…`):

| # | Step | What it does |
|---|---|---|
| 1 | Validation | Checks every choice against the matrix. On error, nothing is created. |
| 2 | Framework | `composer create-project` of the official skeleton (`symfony/skeleton`, `laravel/laravel`, `codeigniter4/appstarter`, `slim/slim-skeleton`), then the packages it needs (e.g. Doctrine for Symfony). |
| 3 | Frontend | Twig/Blade: a branded home page. SPA: official installer (`create-vite`, `ng new`) in `frontend/`, then a home component and an `/api` proxy. |
| 4 | Database | Framework-specific connection settings (DATABASE_URL, DB_CONNECTION, `database.default.*`…) and the **`GET /api/health`** route. |
| 5 | Docker | `Dockerfile` (PHP-FPM + useful extensions), `compose.yaml`, nginx and PHP configuration. |
| 6 | Services | Adds Redis and Mailpit to `compose.yaml` and to the framework configuration. |
| 7 | Brand | Composer name, PHP namespace, CSS theme in the chosen colors, page titles, logo. |
| 8 | Common files | `Makefile`, `README.md`, `.gitignore`, `.editorconfig`, GitHub Actions CI. |
| 9 | Secrets | **Random** passwords and keys, written to an unversioned file. |
| 10 | Git | `git init` and a first commit (can be disabled with `--no-git`). |

A few guarantees:

- **No shell command is built by string concatenation**: every external call passes an argument
  array to Symfony Process.
- **Deterministic**: same choices → same files (secrets aside).
- **Idempotent**: re-running a step duplicates nothing (tagged `.env` blocks, single imports,
  existing secrets kept).
- **No hard-coded secrets**: passwords are generated for each project.

### Where the logic lives

```
config/matrix.yaml                 ← what is offered (versions, compatibility, Docker images)
packages/core/                     ← the whole engine, shared by the CLI and the future website
  src/Matrix/                      ← reading and validating the matrix
  src/Model/                       ← ProjectSpec, Brand (immutable, validated objects)
  src/Generator/Step/              ← the 10 steps, framework-agnostic
  src/Framework/<Name>Profile.php  ← what differs from one framework to another
  src/Frontend/SpaLayouts.php      ← what differs from one SPA frontend to another
  templates/                       ← generated files (Twig): docker/, common/, ci/, frontend/, <framework>/
apps/cli/                          ← the `gabarit` command (no business logic)
```

---

## 5. The generated project

Example for Laravel + MariaDB + Vue + Redis:

```
shop/
├── app/ bootstrap/ config/ …      ← the framework's official skeleton
├── frontend/                      ← the Vue application (SPA only)
├── docker/
│   ├── nginx/default.conf
│   └── php/app.ini, fpm.conf
├── Dockerfile                     ← PHP-FPM + extensions (intl, zip, pdo_mysql, redis…)
├── compose.yaml                   ← app, nginx, database, node, redis…
├── Makefile                       ← make up / down / logs / sh / test
├── README.md                      ← the project's documentation, branded (in French)
├── .github/workflows/ci.yml       ← CI tailored to the framework and frontend
└── .env                           ← secrets (unversioned)
```

### Secrets

| Framework | Secrets file (never versioned) | Versioned configuration |
|---|---|---|
| Symfony | `.env.local` | `.env` (placeholder password) |
| Laravel | `.env` | `.env.example` |
| CodeIgniter | `.env` | `env` (official template) |
| Slim | `.env` (injected into the PHP container) | — |

This file holds `DB_PASSWORD` (plus `DB_ROOT_PASSWORD` for MySQL/MariaDB), which Docker Compose reads
to create the database. It is added to `.gitignore`. On a new machine you have to recreate it: the
generated project's README lists the expected variables.

### The health check

Every project exposes `GET /api/health`, which also queries the database:

```json
{"status": "ok", "app": "Demo Shop", "database": "ok"}
```

It returns `503` with `"database": "error"` when the database is unreachable. It's used by CI and
monitoring.

---

## 6. Starting the generated project

```bash
cd my-app
make up
```

`make up` builds the PHP image, starts the containers, runs `composer install`, then migrations if
the framework needs them (Laravel: `php artisan migrate`). Then:

| URL | Content |
|---|---|
| http://localhost:8080 | the PHP application |
| http://localhost:8080/api/health | the health check |
| http://localhost:5173 | the SPA frontend (Vite or Angular), with hot reload |
| http://localhost:8025 | Mailpit (captured emails) |

Ports can be changed with the `HTTP_PORT`, `FRONT_PORT` and `MAILPIT_PORT` variables, for example
`HTTP_PORT=9000 make up`.

Other commands: `make down`, `make logs`, `make sh` (shell in the PHP container), `make test` (checks
that the application starts and responds), `make help`.

---

## 7. Developing Gabarit

| Command | Purpose |
|---|---|
| `make test` | Unit tests, CLI tests, and generation of a **sample** of the matrix. Fast, no network. |
| `make test-nightly` | Generates the **whole** matrix (656 projects), then one project with the real installers. |
| `make lint` | PHPStan level 8, PHP-CS-Fixer, matrix validation. |
| `make cs-fix` | Fixes code style. |
| `make sh` | Shell in the dev container. |

Run after every change: `make lint` and `make test`.

### How the tests stay fast

The tests download nothing. The installers (composer, create-vite, Angular CLI) are replaced by
**frozen official skeletons** in `packages/core/tests/Fixtures/skeletons/`, copied from real
installations. Each generated project is then validated with `docker compose config` and
`composer validate`.

The `make test` sample covers every framework × frontend, framework × database and framework × PHP
pair. `make test-nightly` (`GABARIT_FULL_MATRIX=1`) covers every combination.

**After bumping a version in the matrix**, regenerate the frozen skeletons from a real installation,
without `vendor/`, `node_modules/`, lock files or secrets.

---

## 8. Extending Gabarit

Golden rule: **a new option = a matrix entry + its templates + its test**. Always check the version
in the official documentation before adding it.

- **A PHP framework**
  1. Entry in `config/matrix.yaml` (versions, compatible PHP, Composer package, databases, frontends,
     `custom_namespace`).
  2. Profile `packages/core/src/Framework/<Name>Profile.php`: database configuration, health check,
     services, secrets, PHP extensions. Register it in `FrameworkProfiles`.
  3. Templates in `packages/core/templates/<name>/`.
  4. Frozen skeleton in `packages/core/tests/Fixtures/skeletons/<name>-<version>/` and its mapping in
     `FakeProcessRunner::skeletonOf()`.
- **An SPA frontend**: entry in the matrix (`kind`, installer at an exact version), profile in
  `packages/core/src/Frontend/SpaLayouts.php`, component in `packages/core/templates/frontend/<name>/`,
  frozen skeleton in `packages/core/tests/Fixtures/skeletons/frontend/<name>/`.
- **A new version of an existing framework**: add it to the matrix and freeze its skeleton.

The generation tests then cover the new combinations automatically.

---

## 9. Troubleshooting

| Symptom | Cause and fix |
|---|---|
| `make: command not found` (Windows) | `choco install make`, or run the Makefile's Docker commands directly. |
| Docker build: "additional privileges requested" | `export COMPOSE_BAKE=false` |
| Odd paths (`C:/Program Files/Git/app`) in Git Bash | Prefix with `MSYS_NO_PATHCONV=1`. |
| `toomanyrequests` during a build | Docker Hub's anonymous pull limit: `docker login`, or wait. |
| Very slow generation on Windows | Docker volumes on NTFS are slow (composer, npm). Expected the first time. |
| 504 on the generated project's very first request | The framework is building its cache (slow on Windows). Reload after a few seconds. |
| The SPA frontend doesn't respond right away | The `node` container installs dependencies on first start (`make logs`). |
| Project files owned by `root` (Linux) | The container runs as root: `sudo chown -R $USER: my-app`. |
| "Le dossier cible existe déjà et n'est pas vide" | Gabarit never overwrites an existing folder: pick another `--dir`. |
| MinIO missing from the services | The `minio/minio` image is no longer distributed on Docker Hub; waiting for a verified alternative. |
