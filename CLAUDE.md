# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

OpenDXP Skeleton — a project template for building digital experience platforms on top of [OpenDXP](https://opendxp.io) (a Pimcore-derived CMS). Backend is PHP 8.4 + Symfony 7.4; frontend is TypeScript + CSS built with Vite.

## Development Environment

The project runs inside [DDEV](https://ddev.readthedocs.io/) (Docker-based). All PHP/Composer/console commands should be run inside DDEV unless you have a native setup.

```bash
ddev start                          # Start containers
ddev stop                           # Stop containers
ddev ssh                            # SSH into web container
ddev exec bin/console [command]     # Run Symfony console commands
ddev composer [command]             # Run Composer
```

## Common Commands

**Frontend**

```bash
ddev pnpm dev       # Vite dev server (port 5173, hot reload)
ddev pnpm build     # TypeScript check + Vite production build → public/build/
ddev pnpm preview   # Preview production build locally
```

**Backend**

```bash
ddev exec bin/console cache:clear
ddev exec bin/console debug:router
ddev exec bin/console doctrine:migrations:migrate
```

**Linting / formatting** is handled by the `oxc` toolchain via a pre-commit hook (`.vite-hooks/pre-commit` runs `vite staged`). Oxlint and oxfmt are used for JS/TS; there is no separate PHP linter configured in this skeleton.

No test suite is configured in this skeleton.

## Architecture

### Backend (PHP / Symfony / OpenDXP)

- `public/index.php` — web entry point; calls `OpenDXP\Bootstrap` then hands off to Symfony's `Kernel`
- `bin/console` — CLI entry point; same bootstrap, returns an OpenDXP `Application`
- `src/Kernel.php` — extends `OpenDxpKernel`; Symfony's service container auto-registers everything under `src/`
- `src/Controller/` — Symfony controllers (attribute-based routing)
- `src/Command/` — Symfony console commands
- `config/` — Symfony configuration; `config.yaml` is the root, with `packages/` for bundle configs and `local/` for git-ignored local overrides
- `var/classes/DataObject/` — auto-generated PHP classes from OpenDXP's class definitions (do not edit manually)

OpenDXP manages the admin panel, asset storage (`var/assets/`), document/page tree, and DataObject persistence via Doctrine + MariaDB.

### Frontend (TypeScript / Vite)

- `assets/main.ts` — sole Vite entry point; imports `styles.css` and any other modules
- `assets/styles.css` — global styles with custom design tokens
- `vite.config.ts` — Vite config using the Symfony plugin; dev server binds to `0.0.0.0:5173` for DDEV
- Build output lands in `public/build/`; Twig templates reference it via `vite_entry_script_tags('app')` / `vite_entry_link_tags('app')`

### Twig Templates

- `templates/base.html.twig` — root layout; includes Vite asset tags
- `templates/base/` — shared partials (header, footer, favicons)
- `templates/default/` — page-level templates

### Environment / Config

| Variable           | Purpose                            |
| ------------------ | ---------------------------------- |
| `REDIS_URL`        | Cache / session store              |
| `MAILER_DSN`       | Mail transport                     |
| `APP_ENV`          | Symfony environment (`dev`/`prod`) |
| `OPENDXP_DEV_MODE` | OpenDXP developer mode flag        |

`.env` holds defaults; `.env.local` holds machine-specific overrides (git-ignored). DDEV injects the real values via `.env.local` on `ddev start`.

## Key Dependencies

| Package                    | Role                                       |
| -------------------------- | ------------------------------------------ |
| `opendxp/opendxp` ^1.3     | CMS core (content, assets, admin panel)    |
| `symfony/*` 7.4            | Framework (routing, DI, security, console) |
| `doctrine/doctrine-bundle` | ORM + MariaDB                              |
| `pentatrion/vite-bundle`   | Symfony ↔ Vite integration                 |
| TypeScript 6.0 / Vite      | Frontend build toolchain                   |
| pnpm 11.0.6                | Node package manager (enforced)            |
