# CLAUDE.md — Gabarit

Générateur de squelettes d'applications PHP en marque blanche. Deux interfaces (CLI + web) au-dessus d'un même cœur.

## 1. Mission

Gabarit génère un projet PHP prêt à démarrer à partir de choix de l'utilisateur :

- framework PHP + version (Symfony, Laravel, CodeIgniter, Slim)
- version de PHP
- base de données (PostgreSQL, MySQL, MariaDB, SQLite)
- framework front (Twig/Blade, Vue 3, React, Svelte, Angular, SolidJS, Preact, aucun)
- services annexes (Redis, Mailpit)
- Docker (Dockerfile, docker compose, nginx)
- identité de marque (nom, logo, couleurs, namespace) — c'est le côté « marque blanche »

Résultat :

- **CLI** : `gabarit new` crée directement le projet complet dans le dossier courant ; il démarre avec `make up`.
- **Web** (modèle Spring Initializr) : l'utilisateur choisit, télécharge un **starter** zip léger, le
  décompresse et lance `bin/up`, qui installe et démarre tout grâce à Docker (voir §6).

## 2. Principes non négociables

- **Un seul cœur.** Toute la logique de génération vit dans `packages/core`. La CLI et le web ne font qu'appeler le cœur. Aucune logique métier dans les contrôleurs ni dans les commandes.
- **Composition en couches, pas de squelettes figés.** On n'embarque jamais le code d'un framework. On appelle son installeur officiel (`composer create-project`, `create-vite`, `ng new`), puis on ajoute nos couches (Docker, env, CI, marque).
- **La matrice de compatibilité est la source de vérité.** Tout choix est validé contre `config/matrix.yaml`. Une combinaison absente de la matrice est refusée, jamais « tentée ».
- **Déterministe et testable.** Même entrée → même sortie (hors secrets générés). Chaque combinaison de la matrice doit pouvoir être générée en CI.
- **Sécurité d'abord côté web.** Voir section 9.

## 3. Stack technique

| Couche | Choix |
|---|---|
| Langage | PHP 8.3, Symfony 7.4 |
| CLI | Symfony Console (+ Laravel Prompts pour les menus interactifs) |
| Processus externes | Symfony Process |
| Templates | Twig (moteur de rendu des fichiers générés) |
| Distribution de la CLI | Image Docker `ghcr.io/ozakdigital/gabarit` (publiée par la CI) + installeurs `install.sh` / `install.ps1` |
| API web | Symfony (contrôleurs simples, sans base de données ni file d'attente) |
| Front web | Vue.js 3 + Vite + TypeScript |
| Tests | PHPUnit + tests de génération de bout en bout |
| Déploiement | Docker sur VPS Infomaniak |

Le web ne stocke rien : le starter est construit en mémoire et envoyé directement au navigateur.

## 4. Structure du dépôt

```
gabarit/
├── CLAUDE.md
├── Makefile
├── docker-compose.yml            # environnement de dev de Gabarit lui-même
├── install.sh, install.ps1       # installation de la CLI (Linux/macOS, Windows)
├── bin/gabarit, bin/gabarit.cmd  # commande `gabarit` installée (lance l'image Docker)
├── config/
│   └── matrix.yaml               # frameworks, versions, compatibilités
├── docker/
│   ├── dev/                      # image de l'environnement de dev
│   └── cli/                      # image de distribution de la CLI
├── packages/
│   └── core/                     # le cœur, sans dépendance à la CLI ni au web
│       ├── src/
│       │   ├── Matrix/           # chargement + validation de la matrice
│       │   ├── Model/            # ProjectSpec, Brand (objets immuables)
│       │   ├── Generator/        # pipeline et étapes (voir §6)
│       │   ├── Framework/        # un profil par framework PHP
│       │   ├── Frontend/         # un profil par front SPA
│       │   ├── Starter/          # starter zip (web) et son application (`gabarit apply`)
│       │   └── Template/         # rendu Twig
│       ├── templates/            # fichiers .twig (docker, common, ci, frontend, starter, <framework>…)
│       └── tests/
└── apps/
    ├── cli/                      # application console `gabarit`
    │   ├── bin/gabarit
    │   └── src/Command/
    └── web/
        ├── api/                  # Symfony : matrice + construction du starter
        └── ui/                   # Vue 3 : formulaire de choix + téléchargement
```

## 5. Modèle de données central

`ProjectSpec` (immuable, validé à la construction) :

```
name             string   slug [a-z0-9-], 2 à 40 caractères
framework        enum     symfony | laravel | codeigniter | slim
frameworkVersion string   doit exister dans la matrice
phpVersion       string   doit être compatible avec frameworkVersion
database         enum     postgresql | mysql | mariadb | sqlite
frontend         enum     twig | blade | vue | react | svelte | angular | solid | preact | inertia-vue | none
services         enum[]   redis | minio | mailpit
brand            Brand    nom d'affichage, namespace PHP, logo (optionnel), couleurs
```

`Brand` alimente les templates (titre, README, `composer.json` name/namespace, thème front).
Les valeurs d'enum non déclarées dans la matrice (`inertia-vue`, `minio`) sont refusées.

## 6. Pipeline de génération

Le cœur expose une seule méthode : `ProjectGenerator::generate(ProjectSpec $spec, string $targetDir): GenerationResult`.

Ordre des étapes (chaque étape est une classe qui implémente `GeneratorStep`) :

1. `ValidateSpecStep` — contrôle contre la matrice
2. `FrameworkInstallStep` — `composer create-project` à la version choisie
3. `FrontendInstallStep` — installation du front choisi
4. `DatabaseConfigStep` — configuration de la base et route `/api/health`, selon le framework
5. `DockerStep` — Dockerfile, docker compose, nginx, `.dockerignore`
6. `ServicesStep` — ajoute Redis, Mailpit au compose et à l'env
7. `BrandStep` — nom, namespace, logo, couleurs
8. `CommonFilesStep` — Makefile, README, `.gitignore`, CI
9. `SecretsStep` — mots de passe aléatoires dans un fichier non versionné
10. `GitInitStep` — `git init` + premier commit (optionnel)

Une étape ne dépend que de `ProjectSpec` et de l'état du dossier cible. Elle doit être idempotente quand c'est possible.
Les étapes sont génériques : ce qui diffère d'un framework à l'autre vit dans `Framework/<Nom>Profile`,
ce qui diffère d'un front SPA à l'autre dans `Frontend/SpaLayouts`.

### Starter (web, modèle Spring Initializr)

Le serveur web n'exécute jamais composer ni npm. `StarterBuilder` produit une archive de quelques Ko :

- `gabarit.json` — la spec validée et la version de l'image Gabarit à utiliser ;
- `bin/up` (Linux, macOS, Git Bash) et `bin/up.cmd` (Windows) ;
- `README.md` et le logo éventuel.

Au premier `bin/up`, l'image `ghcr.io/ozakdigital/gabarit:<version>` exécute `gabarit apply` :
`StarterApplier` relit `gabarit.json`, lance le pipeline complet dans un dossier temporaire du conteneur,
fusionne le résultat dans le dossier du starter, puis initialise git. `bin/up` démarre ensuite les
conteneurs. Les secrets sont donc générés sur la machine de l'utilisateur, jamais sur le serveur.

## 7. Matrice de compatibilité

Fichier `config/matrix.yaml`. Format (extrait) :

```yaml
frameworks:
  laravel:
    label: "Laravel"
    custom_namespace: true          # le namespace « App » peut être remplacé par celui de la marque
    versions:
      "13": { php: ["8.3", "8.4"], package: "laravel/laravel", constraint: "13.*" }
    databases: [postgresql, mysql, mariadb, sqlite]
    frontends: [blade, none, vue, react, svelte, angular, solid, preact]
databases:
  mariadb: { label: "MariaDB", server: true, image: "mariadb:11.8", port: 3306, server_version: "11.8.0-MariaDB" }
  sqlite:  { label: "SQLite (fichier local)", server: false }
frontends:
  react: { label: "React 19 + Vite + TypeScript", kind: vite, installer: "create-vite@9.3.4", template: "react-ts" }
services:
  redis: { label: "Redis", image: "redis:7-alpine", port: 6379 }
docker:
  php_image: "php:{php}-fpm-alpine"
```

Règles :

- Ne jamais inventer une version. Avant d'ajouter une entrée, vérifier la doc officielle du framework.
- Ajouter une entrée à la matrice implique d'ajouter son test de génération.
- Le web (`GET /api/matrix`) et la CLI lisent exactement ce fichier ; l'UI construit ses menus dynamiquement et désactive les options incompatibles.

## 8. Interfaces

### CLI

```
gabarit new <nom>            # mode interactif (menus) ou avec options
gabarit new mon-app --framework=symfony --fw-version=7.4 --php=8.3 \
    --db=postgresql --frontend=vue --services=redis --no-interaction
gabarit apply                # transforme un starter décompressé (gabarit.json) en projet complet
gabarit matrix               # affiche les combinaisons disponibles
gabarit doctor               # vérifie docker, composer, node, git
gabarit self-update          # (lanceur) met à jour Gabarit et reconstruit l'image
```

- Installée par `install.sh` / `install.ps1` ; la commande `gabarit` exécute l'image Docker avec le dossier courant monté.
- Mode interactif par défaut ; `--no-interaction` pour les scripts et la CI.
- Codes de sortie : `0` succès, `1` erreur de validation, `2` erreur d'exécution.
- Sortie lisible en français ; `--json` pour une sortie machine.

### API web

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/matrix` | matrice pour construire le formulaire |
| POST | `/api/starter` | valide les choix et renvoie directement le starter zip |

### UI (Vue 3)

Une page de choix à la manière de start.spring.io : framework → version/PHP → base de données → front → services → marque, puis « Générer » télécharge le zip. Les menus viennent de `/api/matrix`. Aucune règle de compatibilité n'est codée en dur dans l'UI.

## 9. Sécurité (web)

- **Liste blanche stricte** : toutes les valeurs sont validées contre la matrice. Le nom de projet est limité à `[a-z0-9-]`.
- **Jamais de commande shell construite par concaténation** avec une entrée utilisateur. Utiliser Symfony Process avec un tableau d'arguments.
- **Aucune exécution côté serveur** : le web ne lance ni composer, ni npm, ni git ; il ne fait que valider et assembler le starter.
- **Logo uploadé** : PNG ou SVG sans script, taille max, jamais exécuté.
- **Aucun stockage** : le zip est construit puis envoyé ; rien n'est conservé sur le serveur.
- **Rate limiting** sur `POST /api/starter`.
- **Secrets** : aucun mot de passe en dur dans les templates ; générés aléatoirement sur la machine de l'utilisateur.

## 10. Conventions de code

- PHP : PSR-12, `declare(strict_types=1)`, types stricts partout, classes `final` par défaut.
- Pas d'état global ; injection de dépendances par constructeur.
- Objets de valeur immuables (`readonly`).
- Noms de classes et de code en anglais ; messages utilisateur et documentation en français (README également en anglais).
- Un commit = un changement logique. Messages de commit à l'impératif, en français ou anglais, mais cohérents.
- Analyse statique : PHPStan niveau 8 et PHP-CS-Fixer en CI.
- Front : TypeScript strict, composition API de Vue, pas de dépendance inutile.

## 11. Tests

- **Unitaires** : validation de la matrice, rendu de chaque template, `ProjectSpec`, starter.
- **Génération complète** : un échantillon couvrant de la matrice à chaque PR, la totalité en nightly ; chaque projet généré est validé par `docker compose config` et `composer validate`. Les installeurs sont simulés par des squelettes officiels figés (`packages/core/tests/Fixtures/skeletons`).
- **Installeurs réels** (nightly) : génération avec composer/npm réels.
- **API** : tests fonctionnels des endpoints.
- **UI** : tests de composants + un scénario bout en bout du parcours complet.

Commande standard : `make test`.

## 12. Plan de livraison

- **Fait** : cœur + CLI ; Symfony, Laravel, CodeIgniter, Slim ; PostgreSQL, MySQL, MariaDB, SQLite ; Twig, Blade et 6 fronts SPA ; marque ; installation de la CLI sur Windows, macOS, Linux.
- **En cours — Web** : starter zip (`StarterBuilder`, `gabarit apply`), publication de l'image sur GHCR, API Symfony, UI Vue 3, déploiement.
- **Ensuite** : Inertia, mises à jour automatiques de la matrice.

Ne pas passer à la phase suivante tant que les tests de la phase en cours ne sont pas verts.

## 13. Règles de travail pour Claude

- Lire ce fichier et `config/matrix.yaml` avant toute modification.
- Toute nouvelle option (framework, base, front, service) = entrée dans la matrice + template + test de génération. Pas l'un sans les autres.
- Ne pas dupliquer de logique entre `apps/cli` et `apps/web` : si c'est utile aux deux, ça va dans `packages/core`.
- Avant d'ajouter une dépendance, vérifier qu'elle est maintenue et compatible avec PHP 8.3.
- Ne jamais écrire de secret réel dans le dépôt ni dans les templates.
- En cas d'ambiguïté sur une règle de compatibilité, consulter la documentation officielle plutôt que deviner.
- Après chaque changement : `make lint` et `make test`.
- Demander avant de changer une décision d'architecture de ce fichier ; proposer la modification de CLAUDE.md dans la même PR.

## 14. Commandes utiles (Makefile)

```
make up        # démarre l'environnement de dev (docker compose)
make down      # arrête l'environnement
make test      # tests unitaires + génération d'un échantillon de la matrice
make test-nightly  # toute la matrice + installeurs réels
make lint      # PHPStan + PHP-CS-Fixer + validation de la matrice
make test-ui   # tests de composants de l'interface (Vitest)
make test-e2e  # parcours complet sur le site lancé par `make web` (Playwright)
make matrix    # affiche la matrice
make gen       # génère un projet d'exemple dans ./tmp/demo
```
