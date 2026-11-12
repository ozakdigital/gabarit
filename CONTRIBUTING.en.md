# Contributing to Gabarit

[Français](CONTRIBUTING.md) · **English**

Thanks for your help! This guide explains how to set up the environment, check your changes and
open a pull request. The architecture rules live in [CLAUDE.md](CLAUDE.md) (in French): read it
before any significant change.

## 1. Set up the environment

You only need **Docker** and **git**. Three ways to work, pick one:

| Method | For | Start |
|---|---|---|
| **Dev Container** (recommended) | VS Code | Open the folder, then "Reopen in Container". Everything is installed. |
| **make** | Linux, macOS, Windows with make | `make up` |
| **bin/dev** | Windows without make | `bin\dev up` (cmd, PowerShell) or `bin/dev up` (Git Bash) |

`bin/dev` accepts the same commands as the Makefile: `bin/dev test`, `bin/dev lint`,
`bin/dev gen FW=laravel`… Below, replace `make` with `bin/dev` if needed.

Then enable the pre-commit checks (already done in the Dev Container):

```bash
make hooks
```

On every commit they run PHP-CS-Fixer and PHPStan on the changed PHP files, validate the matrix
when it changes and type-check the interface. To skip them once: `git commit --no-verify`.

## 2. Everyday commands

| Command | Purpose |
|---|---|
| `make test` | core, CLI and API tests, generation of a sample of the matrix |
| `make lint` | PHPStan level 8, PHP-CS-Fixer, matrix validation |
| `make cs-fix` | fixes code style |
| `make test-ui` | interface component tests (Vitest) |
| `make web-dev` | site in development mode, reloaded on every save: http://localhost:5173 |
| `make web` then `make test-e2e` | production-like site (http://localhost:8090) and full journey (Playwright) |
| `make gen FW=laravel DB=sqlite FRONT=vue` | generates a real project in `tmp/demo` (also `PHP=`, `SERVICES=`) |
| `make sh` | shell in the dev container |

Before pushing: `make lint` and `make test` (and `make test-ui` if you touch the interface).

> On Windows, folders mounted into Docker are slow: the first `make test` and API calls in
> `web-dev` mode take a few seconds. That is expected.

## 3. Adding an option (framework, database, frontend, service)

Golden rule (CLAUDE.md §13): **one option = one matrix entry + its templates + its test**.
Always check the version in the official documentation before adding it.

1. `config/matrix.yaml`: the entry, with exact versions.
2. The option-specific code: `packages/core/src/Framework/<Name>Profile.php` or
   `packages/core/src/Frontend/SpaLayouts.php`.
3. The templates: `packages/core/templates/<name>/`.
4. The frozen official skeleton: `packages/core/tests/Fixtures/skeletons/` (no `vendor/`,
   `node_modules/`, lock files or secrets).

The generation tests then cover the new combination. Details are in the
[README](README.en.md#8-extending-gabarit).

## 4. Conventions

- PHP: PSR-12, `declare(strict_types=1)`, `final` classes and `readonly` objects by default.
- Code and class names in English; user messages, documentation and commits in French (or English,
  but consistent).
- One commit = one logical change, imperative message: "Add Slim 5 support".
- No secret in the repository or templates: passwords are generated.
- Interface: strict TypeScript, Vue composition API, no unnecessary dependency.

## 5. Opening a pull request

1. Create a branch from `main`.
2. Check that `make lint` and `make test` pass.
3. Fill in the pull request template (its checklist mirrors the rules above).
4. CI runs everything again: lint, tests, interface and the full site journey.

A question, a bug? Open an [issue](https://github.com/ozakdigital/gabarit/issues) with the output of
`gabarit doctor`.
