# CLAUDE.md — Gabarit

Générateur de squelettes d'applications PHP en marque blanche. Deux interfaces (CLI + web) au-dessus d'un même cœur.

## 1. Mission

Gabarit génère un projet PHP prêt à démarrer à partir de choix de l'utilisateur :

- framework PHP + version (Symfony, Laravel, CodeIgniter, Slim…)
- version de PHP
- base de données (PostgreSQL, MySQL, MariaDB, SQLite)
- framework front (Twig/Blade, Vue 3, React, Svelte, Angular, SolidJS, Preact, aucun)
- services annexes (Redis, MinIO, Mailpit)
- Docker (Dockerfile, docker-compose, nginx)
- identité de marque (nom, logo, couleurs, namespace) — c'est le côté « marque blanche »

Résultat : un dossier (CLI) ou une archive zip (web) qui démarre avec `make up`.

## 2. Principes non négociables

- **Un seul cœur.** Toute la logique de génération vit dans `packages/core`. La CLI et le web ne font qu'appeler le cœur. Aucune logique métier dans les contrôleurs ni dans les commandes.
- **Composition en couches, pas de squelettes figés.** On n'embarque jamais le code d'un framework. On appelle son installeur officiel (`composer create-project`, `npm create vite`), puis on ajoute nos couches (Docker, env, CI, marque).
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
| API web | Symfony (API Platform non requis, contrôleurs simples) |
| Front web | Vue.js 3 + Vite + TypeScript |
| Base de données | PostgreSQL |
| File d'attente | Symfony Messenger sur Redis |
| Stockage des zips | MinIO (compatible S3) avec expiration |
| Tests | PHPUnit + tests de génération de bout en bout |
| Déploiement | Docker sur VPS Infomaniak |

## 4. Structure du dépôt

```
gabarit/
├── CLAUDE.md
├── Makefile
├── docker-compose.yml            # environnement de dev de Gabarit lui-même
├── config/
│   └── matrix.yaml               # frameworks, versions, compatibilités
├── packages/
│   └── core/                     # le cœur, sans dépendance à la CLI ni au web
│       ├── src/
│       │   ├── Matrix/           # chargement + validation de la matrice
│       │   ├── Model/            # ProjectSpec, Brand, Choice (objets immuables)
│       │   ├── Generator/        # un générateur par couche (voir §6)
│       │   ├── Template/         # rendu Twig
│       │   └── Archive/          # création du zip
│       ├── templates/            # fichiers .twig (docker, env, ci, common…)
│       └── tests/
├── apps/
│   ├── cli/                      # binaire `gabarit`
│   │   ├── bin/gabarit
│   │   └── src/Command/
│   └── web/
│       ├── api/                  # Symfony : endpoints + workers Messenger
│       └── ui/                   # Vue 3 : formulaire de choix + suivi + téléchargement
└── docs/
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

## 6. Pipeline de génération

Le cœur expose une seule méthode : `ProjectGenerator::generate(ProjectSpec $spec, string $targetDir): GenerationResult`.

Ordre des étapes (chaque étape est une classe qui implémente `GeneratorStep`) :

1. `ValidateSpecStep` — contrôle contre la matrice
2. `FrameworkInstallStep` — `composer create-project` à la version choisie
3. `FrontendInstallStep` — installation du front choisi
4. `DatabaseConfigStep` — `.env`, config Doctrine/Eloquent selon le framework
5. `DockerStep` — Dockerfile, docker-compose, nginx, `.dockerignore`
6. `ServicesStep` — ajoute Redis, MinIO, Mailpit au compose et à l'env
7. `BrandStep` — nom, namespace, logo, couleurs
8. `CommonFilesStep` — Makefile, README, `.gitignore`, CI
9. `SecretsStep` — mots de passe aléatoires dans `.env.local`
10. `GitInitStep` — `git init` + premier commit (optionnel, désactivé côté web)

Une étape ne dépend que de `ProjectSpec` et de l'état du dossier cible. Elle doit être idempotente quand c'est possible.

### Mode « hors ligne » pour le web

Côté web, les étapes qui lancent composer/npm sont lentes et risquées. Stratégie : le worker génère à partir d'instantanés pré-construits du framework (un squelette officiel par version, mis à jour par un job planifié), puis applique les étapes 3 à 9. La CLI, elle, appelle toujours les installeurs officiels.

## 7. Matrice de compatibilité

Fichier `config/matrix.yaml`. Format :

```yaml
frameworks:
  symfony:
    versions:
      "7.4": { php: ["8.2", "8.3", "8.4"] }
    databases: [postgresql, mysql, mariadb, sqlite]
    frontends: [twig, vue, react, none]
  laravel:
    versions:
      "12": { php: ["8.2", "8.3", "8.4"] }
    databases: [postgresql, mysql, sqlite]
    frontends: [blade, vue, react, inertia-vue, none]
services:
  redis:   { image: "redis:7-alpine" }
  minio:   { image: "minio/minio" }
  mailpit: { image: "axllent/mailpit" }
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
    --db=postgresql --frontend=vue --services=redis,minio --no-interaction
gabarit matrix               # affiche les combinaisons disponibles
gabarit doctor               # vérifie docker, composer, node, git
```

- Mode interactif par défaut ; `--no-interaction` pour les scripts et la CI.
- Codes de sortie : `0` succès, `1` erreur de validation, `2` erreur d'exécution.
- Sortie lisible en français ; `--json` pour une sortie machine.

### API web

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/matrix` | matrice pour construire le formulaire |
| POST | `/api/projects` | valide un ProjectSpec, crée un job, renvoie `{id}` |
| GET | `/api/projects/{id}` | statut : `queued`, `running`, `done`, `failed` + étape en cours |
| GET | `/api/projects/{id}/download` | URL signée MinIO vers le zip |

### UI (Vue 3)

Assistant en étapes : framework → version/PHP → base de données → front → services → marque → récapitulatif → progression → téléchargement. Les menus viennent de `/api/matrix`. Aucune règle de compatibilité n'est codée en dur dans l'UI.

## 9. Sécurité (web)

- **Liste blanche stricte** : toutes les valeurs sont validées contre la matrice. Le nom de projet est limité à `[a-z0-9-]`.
- **Jamais de commande shell construite par concaténation** avec une entrée utilisateur. Utiliser Symfony Process avec un tableau d'arguments.
- **Isolation** : les jobs de génération tournent dans un conteneur sans accès réseau sensible, avec limites CPU/mémoire/temps (timeout dur).
- **Logo uploadé** : types MIME autorisés (PNG, SVG nettoyé), taille max, jamais exécuté.
- **Zips temporaires** : expiration automatique (ex. 24 h), nettoyage par tâche planifiée.
- **Rate limiting** sur `POST /api/projects`.
- **Secrets** : aucun mot de passe en dur dans les templates ; générés aléatoirement à chaque projet.

## 10. Conventions de code

- PHP : PSR-12, `declare(strict_types=1)`, types stricts partout, classes `final` par défaut.
- Pas d'état global ; injection de dépendances par constructeur.
- Objets de valeur immuables (`readonly`).
- Noms de classes et de code en anglais ; messages utilisateur et documentation en français.
- Un commit = un changement logique. Messages de commit à l'impératif, en français ou anglais, mais cohérents.
- Analyse statique : PHPStan niveau 8 et PHP-CS-Fixer en CI.
- Front : TypeScript strict, composition API de Vue, pas de dépendance inutile.

## 11. Tests

- **Unitaires** : validation de la matrice, rendu de chaque template, `ProjectSpec`.
- **Génération complète** : pour chaque combinaison de la matrice (ou un échantillon en PR, la totalité en nightly), générer le projet puis exécuter `docker compose config` (syntaxe valide) et, en nightly, `docker compose up -d` suivi d'un test de santé HTTP.
- **API** : tests fonctionnels des endpoints et du worker.
- **UI** : tests de composants + un scénario bout en bout du parcours complet.

Commande standard : `make test`.

## 12. Plan de livraison

- **Phase 1 — MVP cœur + CLI** : Symfony 7.4, PHP 8.3, PostgreSQL et MySQL, front Twig et Vue 3. Étapes 1 à 9 du pipeline. Commandes `new`, `matrix`, `doctor`. Tests de génération.
- **Phase 2 — API + worker** : endpoints, Messenger/Redis, stockage MinIO, expiration. Instantanés pré-construits.
- **Phase 3 — UI web** : assistant Vue 3, suivi de progression, téléchargement.
- **Phase 4 — Extension** : Laravel, MariaDB/SQLite, React, Inertia ; gestion de la marque (logo, couleurs) ; mises à jour automatiques de la matrice.

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
make test      # tests unitaires + fonctionnels
make lint      # PHPStan + PHP-CS-Fixer + lint front
make matrix    # affiche la matrice
make gen       # génère un projet d'exemple dans ./tmp/demo
```
