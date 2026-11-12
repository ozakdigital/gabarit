# Contribuer à Gabarit

**Français** · [English](CONTRIBUTING.en.md)

Merci de votre aide ! Ce guide explique comment installer l'environnement, vérifier vos changements
et proposer une pull request. Les règles d'architecture sont dans [CLAUDE.md](CLAUDE.md) : lisez-le
avant tout changement important.

## 1. Préparer l'environnement

Il vous faut seulement **Docker** et **git**. Trois façons de travailler, au choix :

| Méthode | Pour qui | Démarrer |
|---|---|---|
| **Dev Container** (recommandé) | VS Code | Ouvrez le dossier, puis « Reopen in Container ». Tout est installé. |
| **make** | Linux, macOS, Windows avec make | `make up` |
| **bin/dev** | Windows sans make | `bin\dev up` (cmd, PowerShell) ou `bin/dev up` (Git Bash) |

`bin/dev` accepte les mêmes commandes que le Makefile : `bin/dev test`, `bin/dev lint`,
`bin/dev gen FW=laravel`… Dans la suite, remplacez `make` par `bin/dev` si besoin.

Activez ensuite les vérifications avant commit (déjà fait dans le Dev Container) :

```bash
make hooks
```

À chaque commit, elles lancent PHP-CS-Fixer et PHPStan sur les fichiers PHP modifiés, valident la
matrice si elle change et vérifient les types de l'interface. Pour passer outre une fois :
`git commit --no-verify`.

## 2. Les commandes du quotidien

| Commande | Rôle |
|---|---|
| `make test` | tests du cœur, de la CLI et de l'API, génération d'un échantillon de la matrice |
| `make lint` | PHPStan niveau 8, PHP-CS-Fixer, validation de la matrice |
| `make cs-fix` | corrige le style de code |
| `make test-ui` | tests de composants de l'interface (Vitest) |
| `make web-dev` | site en mode développement, rechargé à chaque sauvegarde : http://localhost:5173 |
| `make web` puis `make test-e2e` | site comme en production (http://localhost:8090) et parcours complet (Playwright) |
| `make gen FW=laravel DB=sqlite FRONT=vue` | génère un vrai projet dans `tmp/demo` (aussi `PHP=`, `SERVICES=`) |
| `make sh` | shell dans le conteneur de dev |

Avant de pousser : `make lint` et `make test` (et `make test-ui` si vous touchez à l'interface).

> Sous Windows, les dossiers montés dans Docker sont lents : le premier `make test` et les appels à
> l'API en mode `web-dev` prennent quelques secondes. C'est normal.

## 3. Ajouter une option (framework, base, front, service)

Règle d'or (CLAUDE.md §13) : **une option = une entrée de matrice + ses templates + son test**.
Vérifiez toujours la version dans la documentation officielle avant de l'ajouter.

1. `config/matrix.yaml` : l'entrée, avec des versions exactes.
2. Le code propre à l'option : `packages/core/src/Framework/<Nom>Profile.php` ou
   `packages/core/src/Frontend/SpaLayouts.php`.
3. Les templates : `packages/core/templates/<nom>/`.
4. Le squelette officiel figé : `packages/core/tests/Fixtures/skeletons/` (sans `vendor/`,
   `node_modules/`, fichiers de lock ni secrets).

Les tests de génération couvrent alors la nouvelle combinaison. Le détail est dans le
[README](README.md#8-étendre-gabarit).

## 4. Conventions

- PHP : PSR-12, `declare(strict_types=1)`, classes `final` et objets `readonly` par défaut.
- Code et noms de classes en anglais ; messages, documentation et commits en français (ou en
  anglais, mais cohérents).
- Un commit = un changement logique, message à l'impératif : « Ajoute le support de Slim 5 ».
- Aucun secret dans le dépôt ni dans les templates : les mots de passe sont générés.
- Interface : TypeScript strict, composition API de Vue, pas de dépendance inutile.

## 5. Proposer une pull request

1. Créez une branche depuis `main`.
2. Vérifiez que `make lint` et `make test` passent.
3. Remplissez le modèle de pull request (la checklist reprend les règles ci-dessus).
4. La CI relance tout : lint, tests, interface et parcours complet du site.

Une question, un bug ? Ouvrez une [issue](https://github.com/ozakdigital/gabarit/issues) avec la
sortie de `gabarit doctor`.
