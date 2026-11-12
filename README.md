# Gabarit

**Français** · [English](README.en.md)

Générateur de projets PHP **en marque blanche**. Choisissez votre framework (Symfony, Laravel,
CodeIgniter, Slim), votre base de données, votre front et votre marque : Gabarit crée un projet
complet et dockerisé, prêt à démarrer. **Seul Docker est nécessaire** sur votre machine.

Deux façons de l'utiliser :

- **En ligne de commande** : installez la CLI une fois, puis `gabarit new mon-app`.
- **Depuis le site** : choisissez vos options, téléchargez le zip, décompressez-le et lancez
  `bin/up`. Docker installe et démarre tout.

---

## Sommaire

1. [Ce que Gabarit sait générer](#1-ce-que-gabarit-sait-générer)
2. [Installer Gabarit](#2-installer-gabarit)
3. [Générer un projet](#3-générer-un-projet)
4. [Ce qui se passe pendant la génération](#4-ce-qui-se-passe-pendant-la-génération)
5. [Le projet généré](#5-le-projet-généré)
6. [Démarrer le projet généré](#6-démarrer-le-projet-généré)
7. [Développer Gabarit](#7-développer-gabarit)
8. [Étendre Gabarit](#8-étendre-gabarit)
9. [Dépannage](#9-dépannage)

---

## 1. Ce que Gabarit sait générer

Toutes les combinaisons proposées sont listées dans [config/matrix.yaml](config/matrix.yaml), la
**source de vérité unique** : une combinaison absente de ce fichier est refusée.

| Framework | Version | PHP | Front rendu serveur | Namespace de marque |
|---|---|---|---|---|
| Symfony | 7.4 LTS | 8.2 – 8.4 | Twig | oui |
| Laravel | 13 | 8.3 – 8.4 | Blade | oui |
| CodeIgniter | 4.7 | 8.2 – 8.4 | — | non (`App` imposé par le framework) |
| Slim | 4 | 8.2 – 8.4 | — | oui |

- **Bases de données** (tous frameworks) : PostgreSQL 17, MySQL 8.4, MariaDB 11.8, SQLite
  (un simple fichier dans le projet, sans conteneur).
- **Fronts** (tous frameworks) :
  - rendu serveur : Twig (Symfony), Blade (Laravel), ou **aucun** (API seule) ;
  - application séparée (SPA) dans `frontend/` : Vue 3, React 19, Svelte 5, SolidJS, Preact
    (créés par create-vite 9.3.4) et Angular 22 (créé par Angular CLI 22.2.6).
- **Services annexes** : Redis, Mailpit (capture des e-mails en développement).
- **Marque** : nom d'affichage, namespace PHP, couleurs, logo (PNG ou SVG).

Pour voir la matrice à jour : `make matrix`.

---

## 2. Installer Gabarit

### Prérequis

| Outil | Pourquoi |
|---|---|
| **Docker** (Docker Desktop sous Windows et macOS, Docker Engine sous Linux) | la CLI tourne dans un conteneur, et les projets générés aussi |
| **git** | télécharger et mettre à jour Gabarit |

**Aucun PHP, Composer ni Node n'est nécessaire sur votre machine** : l'image `gabarit-cli`
contient PHP 8.3, Composer, Node, git et le code de Gabarit.

### Installation en une commande

**Linux et macOS** (terminal) :

```bash
curl -fsSL https://raw.githubusercontent.com/ozakdigital/gabarit/main/install.sh | sh
```

**Windows** (PowerShell) :

```powershell
irm https://raw.githubusercontent.com/ozakdigital/gabarit/main/install.ps1 | iex
```

L'installeur :

1. vérifie que Docker et git sont présents et que Docker répond ;
2. télécharge Gabarit dans `~/.gabarit` (`%USERPROFILE%\.gabarit` sous Windows), ou le met à jour ;
3. construit l'image Docker `gabarit-cli` (quelques minutes la première fois) ;
4. installe la commande `gabarit` : lien dans `~/.local/bin` (Linux, macOS), ou ajout de
   `.gabarit\bin` au PATH de l'utilisateur (Windows).

Ouvrez ensuite **un nouveau terminal** et vérifiez :

```bash
gabarit --version
gabarit doctor
```

> Sous Linux et macOS, si `~/.local/bin` n'est pas dans votre PATH, l'installeur affiche la ligne à
> ajouter à `~/.bashrc` ou `~/.zshrc`. La commande fonctionne aussi dans Git Bash sous Windows.

### Mettre à jour, désinstaller

```bash
gabarit self-update     # récupère la dernière version et reconstruit l'image
```

Pour désinstaller : supprimez le dossier `~/.gabarit`, le lien `~/.local/bin/gabarit` (ou l'entrée
`.gabarit\bin` du PATH sous Windows), puis l'image : `docker image rm gabarit-cli`.

---

## 3. Générer un projet

Placez-vous dans le dossier où vous rangez vos projets, puis :

```bash
gabarit new mon-app
```

Le projet est créé dans **`./mon-app`, sous le dossier courant**. La commande `gabarit` lance la CLI
dans l'image Docker en y montant le dossier courant ; sous Linux et macOS, les fichiers créés vous
appartiennent (pas à `root`).

Les commandes disponibles :

| Commande | Rôle |
|---|---|
| `gabarit new <nom>` | génère un projet (interactif ou avec options) |
| `gabarit apply` | installe un starter téléchargé depuis le site (dossier contenant `gabarit.json`) |
| `gabarit matrix` | affiche les combinaisons disponibles (`--json` pour une machine) |
| `gabarit doctor` | vérifie les outils nécessaires |
| `gabarit self-update` | met Gabarit à jour |
| `gabarit help <commande>` | aide détaillée d'une commande |

### Mode interactif (par défaut)

Gabarit pose les questions manquantes, avec des menus construits **depuis la matrice** : seules les
options compatibles avec vos choix précédents sont proposées.

```
Framework            › Symfony / Laravel / CodeIgniter / Slim
Version de PHP       › 8.3 / 8.4 …          (n'affiche que les versions compatibles)
Base de données      › PostgreSQL / MySQL / MariaDB / SQLite
Front                › Twig / Aucun / Vue 3 / React …
Services annexes     › Redis, Mailpit       (cases à cocher)
Nom d'affichage      › Mon App
Namespace PHP        › App                  (pas demandé pour CodeIgniter)
```

Une question n'est pas posée quand il n'y a qu'un seul choix possible (par exemple la version de
Laravel).

### Mode non interactif (scripts, CI)

Toutes les réponses passent en options :

```bash
gabarit new boutique \
    --framework=laravel --fw-version=13 --php=8.3 \
    --db=mariadb --frontend=blade --services=redis,mailpit \
    --brand-name="Boutique Démo" --namespace='Acme\Shop' \
    --primary-color="#e11d48" --no-interaction
```

| Option | Valeurs | Défaut sans interaction |
|---|---|---|
| `<nom>` (argument) | slug `[a-z0-9-]`, 2 à 40 caractères | obligatoire |
| `--framework` | `symfony`, `laravel`, `codeigniter`, `slim` | `symfony` |
| `--fw-version` | selon la matrice (`7.4`, `13`, `4.7`, `4`) | la version de la matrice |
| `--php` | selon le framework | `8.3` |
| `--db` | `postgresql`, `mysql`, `mariadb`, `sqlite` | `postgresql` |
| `--frontend` | `twig`, `blade`, `none`, `vue`, `react`, `svelte`, `angular`, `solid`, `preact` | le 1er front du framework |
| `--services` | `redis,mailpit` ou `none` | aucun |
| `--brand-name` | lettres, chiffres, espaces, `. , ' & -` | déduit du nom (`mon-app` → `Mon App`) |
| `--namespace` | `Acme` ou `Acme\Shop` (PascalCase) | `App` |
| `--primary-color`, `--secondary-color` | `#rrggbb` | `#4f46e5`, `#0f172a` |
| `--logo` | chemin **relatif au dossier courant** vers un PNG ou SVG (1 Mo max, SVG sans script) | aucun |
| `--dir` | dossier cible, **relatif au dossier courant** (vide ou absent) | `./<nom>` |
| `--no-git` | ne pas créer de dépôt git | git activé |
| `--json` | sortie machine (implique `--no-interaction`) | — |

**Codes de sortie** : `0` succès, `1` choix invalides (rien n'a été créé), `2` erreur pendant la
génération (installeur en échec, dossier non vide…).

Une combinaison incompatible est refusée **avant** toute installation, avec un message explicite :

```
[ERROR] Choix invalides.
        phpVersion : PHP 8.2 n'est pas compatible avec Laravel 13 (compatibles : 8.3, 8.4).
```

> Le conteneur ne voit que le dossier courant : `--dir` et `--logo` doivent désigner un chemin
> **sous** ce dossier (`--dir=clients/acme`, `--logo=./logo.svg`). Pour générer ailleurs,
> placez-vous d'abord dans le dossier voulu (`cd`).

Ajoutez `-v` à `new` pour voir en direct la sortie de composer et npm.

> **Scripts sous Windows (cmd, PowerShell)** : si l'entrée est redirigée (pas de terminal),
> définissez `GABARIT_NO_TTY=1` avant d'appeler `gabarit`.

### Depuis le site web (sans rien installer d'autre que Docker)

Le site fonctionne comme [start.spring.io](https://start.spring.io) :

1. choisissez framework, PHP, base, front, services et marque, puis cliquez sur **Générer** ;
2. le navigateur télécharge un **starter** zip de quelques Ko (`gabarit.json`, `bin/up`, `bin/up.cmd`,
   `README.md`, logo éventuel) ;
3. décompressez-le, puis lancez :

   | Système | Commande |
   |---|---|
   | Linux, macOS, Git Bash | `./bin/up` |
   | Windows (cmd, PowerShell) | `bin\up.cmd` |

Au premier lancement, `bin/up` télécharge l'image Gabarit (`ghcr.io/ozakdigital/gabarit`), qui
exécute `gabarit apply` : installation du framework et du front avec leurs installeurs officiels,
configuration, marque et **secrets générés sur votre machine**. Puis `bin/up` démarre les conteneurs.
Les lancements suivants démarrent simplement le projet.

Le serveur web ne lance aucun installeur et ne stocke rien : le zip est construit puis envoyé.

`gabarit apply` est aussi utilisable directement : dans un dossier contenant un `gabarit.json`,
`gabarit apply` le transforme en projet complet.

---

## 4. Ce qui se passe pendant la génération

Gabarit **n'embarque aucun code de framework**. Il appelle l'installeur officiel à la version de la
matrice, puis ajoute ses propres couches. La génération suit toujours ces 10 étapes, affichées à
l'écran (`[3/10] Installation du front…`) :

| # | Étape | Ce qu'elle fait |
|---|---|---|
| 1 | Validation | Vérifie chaque choix contre la matrice. En cas d'erreur, rien n'est créé. |
| 2 | Framework | `composer create-project` du squelette officiel (`symfony/skeleton`, `laravel/laravel`, `codeigniter4/appstarter`, `slim/slim-skeleton`), puis les paquets nécessaires (ex. Doctrine pour Symfony). |
| 3 | Front | Twig/Blade : page d'accueil à la marque. SPA : installeur officiel (`create-vite`, `ng new`) dans `frontend/`, puis composant d'accueil et proxy `/api`. |
| 4 | Base de données | Configuration de connexion propre au framework (DATABASE_URL, DB_CONNECTION, `database.default.*`…) et route **`GET /api/health`**. |
| 5 | Docker | `Dockerfile` (PHP-FPM + extensions utiles), `compose.yaml`, configuration nginx et PHP. |
| 6 | Services | Ajoute Redis et Mailpit au `compose.yaml` et à la configuration du framework. |
| 7 | Marque | Nom Composer, namespace PHP, thème CSS aux couleurs choisies, titre des pages, logo. |
| 8 | Fichiers communs | `Makefile`, `README.md`, `.gitignore`, `.editorconfig`, CI GitHub Actions. |
| 9 | Secrets | Mots de passe et clés **aléatoires**, écrits dans un fichier non versionné. |
| 10 | Git | `git init` et premier commit (désactivable avec `--no-git`). |

Quelques garanties :

- **Aucune commande shell n'est construite par concaténation** : chaque appel externe passe un
  tableau d'arguments à Symfony Process.
- **Déterministe** : mêmes choix → mêmes fichiers (hors secrets).
- **Idempotent** : relancer une étape ne duplique rien (blocs de `.env` balisés, imports uniques,
  secrets existants conservés).
- **Aucun secret en dur** : les mots de passe sont générés pour chaque projet.

### Où vit la logique

```
config/matrix.yaml                 ← ce qui est proposé (versions, compatibilités, images Docker)
packages/core/                     ← tout le moteur, partagé par la CLI et le futur site web
  src/Matrix/                      ← lecture et validation de la matrice
  src/Model/                       ← ProjectSpec, Brand (objets immuables, validés)
  src/Generator/Step/              ← les 10 étapes, génériques
  src/Framework/<Nom>Profile.php   ← ce qui diffère d'un framework à l'autre
  src/Frontend/SpaLayouts.php      ← ce qui diffère d'un front SPA à l'autre
  templates/                       ← fichiers générés (Twig) : docker/, common/, ci/, frontend/, <framework>/
apps/cli/                          ← la commande `gabarit` (aucune logique métier)
```

---

## 5. Le projet généré

Exemple pour Laravel + MariaDB + Vue + Redis :

```
boutique/
├── app/ bootstrap/ config/ …      ← le squelette officiel du framework
├── frontend/                      ← l'application Vue (SPA uniquement)
├── docker/
│   ├── nginx/default.conf
│   └── php/app.ini, fpm.conf
├── Dockerfile                     ← PHP-FPM + extensions (intl, zip, pdo_mysql, redis…)
├── compose.yaml                   ← app, nginx, database, node, redis…
├── Makefile                       ← make up / down / logs / sh / test
├── README.md                      ← documentation du projet, à sa marque
├── .github/workflows/ci.yml       ← CI adaptée au framework et au front
└── .env                           ← secrets (non versionné)
```

### Les secrets

| Framework | Fichier des secrets (jamais versionné) | Configuration versionnée |
|---|---|---|
| Symfony | `.env.local` | `.env` (mot de passe factice) |
| Laravel | `.env` | `.env.example` |
| CodeIgniter | `.env` | `env` (modèle officiel) |
| Slim | `.env` (injecté dans le conteneur PHP) | — |

Ce fichier contient notamment `DB_PASSWORD` (et `DB_ROOT_PASSWORD` pour MySQL/MariaDB), lus par
Docker Compose pour créer la base. Il est ajouté au `.gitignore`. Sur un nouveau poste, il faut le
recréer : le README du projet généré liste les variables attendues.

### Le test de santé

Chaque projet expose `GET /api/health`, qui interroge aussi la base :

```json
{"status": "ok", "app": "Boutique Démo", "database": "ok"}
```

Il renvoie `503` et `"database": "error"` si la base est injoignable. Il sert à la CI et à la
supervision.

---

## 6. Démarrer le projet généré

```bash
cd mon-app
make up
```

`make up` construit l'image PHP, démarre les conteneurs, lance `composer install`, puis les
migrations si le framework en a besoin (Laravel : `php artisan migrate`). Ensuite :

| Adresse | Contenu |
|---|---|
| http://localhost:8080 | l'application PHP |
| http://localhost:8080/api/health | le test de santé |
| http://localhost:5173 | le front SPA (Vite ou Angular), avec rechargement à chaud |
| http://localhost:8025 | Mailpit (e-mails capturés) |

Les ports se changent avec les variables `HTTP_PORT`, `FRONT_PORT` et `MAILPIT_PORT`, par exemple
`HTTP_PORT=9000 make up`.

Autres commandes : `make down`, `make logs`, `make sh` (shell dans le conteneur PHP), `make test`
(vérifie que l'application démarre et répond), `make help`.

---

## 7. Développer Gabarit

Les règles d'architecture du projet sont dans [CLAUDE.md](CLAUDE.md).

Pour travailler sur Gabarit lui-même (pas seulement l'utiliser), il faut en plus `make`
(sous Windows : `choco install make`) :

```bash
git clone https://github.com/ozakdigital/gabarit.git
cd gabarit
make up      # conteneur de dev (PHP 8.3, Composer, Node, client Docker) + dépendances
make test
```

Pour essayer vos modifications avec la vraie commande : `GABARIT_SOURCE=$(pwd) sh install.sh`
(Linux, macOS) ou `$env:GABARIT_SOURCE = (Get-Location); .\install.ps1` (Windows), qui
installent `gabarit` depuis votre copie locale. Après une modification, `gabarit self-update`
reconstruit l'image.

> **Sans make** : chaque cible du [Makefile](Makefile) est une simple commande Docker, par exemple
> `docker compose run --rm -T php composer install`. Sous Git Bash, préfixez par
> `MSYS_NO_PATHCONV=1`, et exportez `COMPOSE_BAKE=false` si le build échoue avec « additional
> privileges requested ».

| Commande | Rôle |
|---|---|
| `make test` | Tests unitaires, CLI, et génération d'un **échantillon** de la matrice. Rapide, sans réseau. |
| `make test-nightly` | Génère **toute** la matrice (656 projets), puis un projet avec les installeurs réels. |
| `make lint` | PHPStan niveau 8, PHP-CS-Fixer, validation de la matrice. |
| `make cs-fix` | Corrige le style de code. |
| `make sh` | Shell dans le conteneur de dev. |

À lancer après chaque changement : `make lint` et `make test`.

### Le site web

| Dossier | Rôle |
|---|---|
| `apps/web/api` | API Symfony : `GET /api/matrix`, `POST /api/starter` (zip envoyé directement) |
| `apps/web/ui` | interface Vue 3 + Vite + TypeScript |
| `apps/web/Dockerfile`, `compose.yaml` | images `api` (PHP-FPM) et `web` (nginx + interface) |

```bash
make web        # construit et lance le site : http://localhost:8090
make web-down   # l'arrête
```

En développement de l'interface : `cd apps/web/ui && npm install && npm run dev`
(http://localhost:5173, les appels `/api` sont relayés vers `API_URL`, par défaut `http://localhost:8000`).

**Héberger le site** (VPS) : copiez le dépôt, puis

```bash
APP_SECRET=$(openssl rand -hex 16) WEB_PORT=80 docker compose -f apps/web/compose.yaml up -d --build
```

Le site ne nécessite ni base de données ni stockage. Placez-le derrière votre proxy HTTPS habituel.
Les starters pointent vers l'image `ghcr.io/ozakdigital/gabarit:<version>`, publiée par la CI
(workflow `Image`) à chaque push sur `main` : elle doit être **publique** sur GitHub (Packages →
gabarit → Package settings → Change visibility) pour que `bin/up` puisse la télécharger.

### Comment les tests restent rapides

Les tests ne téléchargent rien. Les installeurs (composer, create-vite, Angular CLI) sont remplacés
par des **squelettes officiels figés** dans `packages/core/tests/Fixtures/skeletons/`, copiés depuis
de vraies installations. Chaque projet généré est ensuite validé par `docker compose config` et
`composer validate`.

L'échantillon de `make test` couvre chaque paire framework × front, framework × base et
framework × PHP. `make test-nightly` (`GABARIT_FULL_MATRIX=1`) les couvre toutes.

**Après une montée de version dans la matrice**, régénérez les squelettes figés depuis une vraie
installation, sans `vendor/`, `node_modules/`, fichiers de lock ni secrets.

---

## 8. Étendre Gabarit

Règle d'or : **une nouvelle option = une entrée de matrice + ses templates + son test**. Vérifiez
toujours la version dans la documentation officielle avant de l'ajouter.

- **Un framework PHP**
  1. Entrée dans `config/matrix.yaml` (versions, PHP compatibles, paquet Composer, bases, fronts,
     `custom_namespace`).
  2. Profil `packages/core/src/Framework/<Nom>Profile.php` : configuration de la base, test de santé,
     services, secrets, extensions PHP. Enregistrez-le dans `FrameworkProfiles`.
  3. Templates dans `packages/core/templates/<nom>/`.
  4. Squelette figé dans `packages/core/tests/Fixtures/skeletons/<nom>-<version>/` et sa
     correspondance dans `FakeProcessRunner::skeletonOf()`.
- **Un front SPA** : entrée dans la matrice (`kind`, installeur à version exacte), profil dans
  `packages/core/src/Frontend/SpaLayouts.php`, composant dans `packages/core/templates/frontend/<nom>/`,
  squelette figé dans `packages/core/tests/Fixtures/skeletons/frontend/<nom>/`.
- **Une nouvelle version d'un framework existant** : ajoutez-la à la matrice et figez son squelette.

Les tests de génération couvrent alors automatiquement les nouvelles combinaisons.

---

## 9. Dépannage

| Symptôme | Cause et solution |
|---|---|
| `make: command not found` (Windows) | `choco install make`, ou lancez les commandes Docker du Makefile directement. |
| Build Docker : « additional privileges requested » | `export COMPOSE_BAKE=false` |
| Chemins bizarres (`C:/Program Files/Git/app`) sous Git Bash | Préfixez par `MSYS_NO_PATHCONV=1`. |
| `toomanyrequests` pendant un build | Limite de téléchargements anonymes de Docker Hub : `docker login`, ou patientez. |
| Génération très lente sous Windows | Les volumes Docker sur NTFS sont lents (composer, npm). C'est normal la première fois. |
| 504 à la toute première requête du projet généré | Le framework construit son cache (lent sous Windows). Rechargez après quelques secondes. |
| Le front SPA ne répond pas tout de suite | Le conteneur `node` installe les dépendances au premier démarrage (`make logs`). |
| Fichiers du projet appartenant à `root` (Linux) | Le conteneur tourne en root : `sudo chown -R $USER: mon-app`. |
| « Le dossier cible existe déjà et n'est pas vide » | Gabarit n'écrase jamais un dossier existant : choisissez un autre `--dir`. |
| MinIO absent des services | L'image `minio/minio` n'est plus distribuée sur Docker Hub ; en attente d'une alternative vérifiée. |
