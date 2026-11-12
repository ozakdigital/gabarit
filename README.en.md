# Gabarit

[Français](README.md) · **English**

A **white-label** PHP project generator. Pick your framework (Symfony, Laravel, CodeIgniter, Slim),
your database, your frontend and your brand: Gabarit creates a complete, dockerized project, ready
to start. **Docker is all you need** on your machine.

Two ways to use it:

- **From the command line**: install the CLI once, then `gabarit new my-app`.
- **From the website**: pick your options, download the zip, unzip it and run `bin/up`. Docker
  installs and starts everything.

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
| **Docker** (Docker Desktop on Windows and macOS, Docker Engine on Linux) | the CLI runs in a container, and so do the generated projects |
| **git** | to download and update Gabarit |

**You don't need PHP, Composer or Node on your machine**: the `gabarit-cli` image ships PHP 8.3,
Composer, Node, git and Gabarit's code.

### One-command install

**Linux and macOS** (terminal):

```bash
curl -fsSL https://raw.githubusercontent.com/ozakdigital/gabarit/main/install.sh | sh
```

**Windows** (PowerShell):

```powershell
irm https://raw.githubusercontent.com/ozakdigital/gabarit/main/install.ps1 | iex
```

The installer:

1. checks that Docker and git are present and that Docker responds;
2. downloads Gabarit into `~/.gabarit` (`%USERPROFILE%\.gabarit` on Windows), or updates it;
3. builds the `gabarit-cli` Docker image (a few minutes the first time);
4. installs the `gabarit` command: a link in `~/.local/bin` (Linux, macOS), or `.gabarit\bin` added
   to the user PATH (Windows).

Then open **a new terminal** and check:

```bash
gabarit --version
gabarit doctor
```

> On Linux and macOS, if `~/.local/bin` isn't in your PATH, the installer prints the line to add to
> `~/.bashrc` or `~/.zshrc`. The command also works in Git Bash on Windows.

### Updating, uninstalling

```bash
gabarit self-update     # fetches the latest version and rebuilds the image
```

To uninstall: delete the `~/.gabarit` folder, the `~/.local/bin/gabarit` link (or the
`.gabarit\bin` PATH entry on Windows), then the image: `docker image rm gabarit-cli`.

---

## 3. Generating a project

Go to the folder where you keep your projects, then:

```bash
gabarit new my-app
```

The project is created in **`./my-app`, under the current folder**. The `gabarit` command runs the
CLI in the Docker image with the current folder mounted; on Linux and macOS, the created files
belong to you (not to `root`).

Available commands:

| Command | Purpose |
|---|---|
| `gabarit new <name>` | generates a project (interactive or with options) |
| `gabarit apply` | installs a starter downloaded from the website (folder containing `gabarit.json`) |
| `gabarit matrix` | shows the available combinations (`--json` for a machine) |
| `gabarit doctor` | checks the required tools |
| `gabarit self-update` | updates Gabarit |
| `gabarit help <command>` | detailed help for a command |

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
gabarit new shop \
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
| `--logo` | path **relative to the current folder** to a PNG or SVG (1 MB max, SVG without scripts) | none |
| `--dir` | target folder, **relative to the current folder** (empty or missing) | `./<name>` |
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

> The container only sees the current folder: `--dir` and `--logo` must point **inside** it
> (`--dir=clients/acme`, `--logo=./logo.svg`). To generate elsewhere, `cd` to that folder first.

Add `-v` to `new` to see composer and npm output live.

> **Windows scripts (cmd, PowerShell)**: if input is redirected (no terminal), set
> `GABARIT_NO_TTY=1` before calling `gabarit`.

### From the website (nothing to install but Docker)

The website works like [start.spring.io](https://start.spring.io):

1. pick framework, PHP, database, frontend, services and brand, then click **Générer** (Generate);
2. the browser downloads a **starter** zip of a few KB (`gabarit.json`, `bin/up`, `bin/up.cmd`,
   `README.md`, optional logo);
3. unzip it, then run:

   | System | Command |
   |---|---|
   | Linux, macOS, Git Bash | `./bin/up` |
   | Windows (cmd, PowerShell) | `bin\up.cmd` |

On first run, `bin/up` downloads the Gabarit image (`ghcr.io/ozakdigital/gabarit`), which runs
`gabarit apply`: framework and frontend installed with their official installers, configuration,
branding and **secrets generated on your machine**. Then `bin/up` starts the containers. Later runs
just start the project.

The web server runs no installer and stores nothing: the zip is built, then sent.

`gabarit apply` can also be used directly: in a folder containing a `gabarit.json`, `gabarit apply`
turns it into a full project.

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

The project's architecture rules are in [CLAUDE.md](CLAUDE.md) (in French).

To work on Gabarit itself (not just use it), you also need `make` (on Windows:
`choco install make`):

```bash
git clone https://github.com/ozakdigital/gabarit.git
cd gabarit
make up      # dev container (PHP 8.3, Composer, Node, Docker client) + dependencies
make test
```

To try your changes with the real command: `GABARIT_SOURCE=$(pwd) sh install.sh` (Linux, macOS)
or `$env:GABARIT_SOURCE = (Get-Location); .\install.ps1` (Windows), which install `gabarit` from
your local copy. After a change, `gabarit self-update` rebuilds the image.

> **Without make**: every target in the [Makefile](Makefile) is a plain Docker command, for example
> `docker compose run --rm -T php composer install`. In Git Bash, prefix with `MSYS_NO_PATHCONV=1`,
> and export `COMPOSE_BAKE=false` if the build fails with "additional privileges requested".

| Command | Purpose |
|---|---|
| `make test` | Unit tests, CLI tests, and generation of a **sample** of the matrix. Fast, no network. |
| `make test-nightly` | Generates the **whole** matrix (656 projects), then one project with the real installers. |
| `make lint` | PHPStan level 8, PHP-CS-Fixer, matrix validation. |
| `make cs-fix` | Fixes code style. |
| `make sh` | Shell in the dev container. |

Run after every change: `make lint` and `make test`.

### The website

| Folder | Purpose |
|---|---|
| `apps/web/api` | Symfony API: `GET /api/matrix`, `POST /api/starter` (zip sent directly) |
| `apps/web/ui` | Vue 3 + Vite + TypeScript interface |
| `apps/web/Dockerfile`, `compose.yaml` | `api` (PHP-FPM) and `web` (nginx + interface) images |

```bash
make web        # builds and starts the site: http://localhost:8090
make web-down   # stops it
```

When working on the interface: `cd apps/web/ui && npm install && npm run dev`
(http://localhost:5173, `/api` calls are proxied to `API_URL`, `http://localhost:8000` by default).

**Hosting the site** (VPS): copy the repository, then

```bash
APP_SECRET=$(openssl rand -hex 16) WEB_PORT=80 docker compose -f apps/web/compose.yaml up -d --build
```

The site needs no database and no storage. Put it behind your usual HTTPS proxy. Starters point to
the `ghcr.io/ozakdigital/gabarit:<version>` image, published by CI (the `Image` workflow) on every
push to `main`: it must be **public** on GitHub (Packages → gabarit → Package settings → Change
visibility) so that `bin/up` can download it.

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
