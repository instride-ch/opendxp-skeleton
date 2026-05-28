# OpenDXP Skeleton

A project template for building digital experience platforms on top of [OpenDXP](https://opendxp.io) — a Pimcore-derived CMS. Built and maintained by [instride AG](https://instride.ch).

## Stack

| Layer     | Technology                                            |
| --------- | ----------------------------------------------------- |
| CMS       | OpenDXP ^1.3 (Pimcore-based)                         |
| Backend   | PHP 8.4 · Symfony 7.4 · Doctrine ORM · MariaDB 11.8  |
| Cache     | Redis · Full-page cache (prod)                       |
| Frontend  | TypeScript 6 · Vite 8 · Tailwind CSS 4               |
| Dev env   | DDEV (Docker) · nginx-fpm · Node 24 · pnpm 11        |

## Prerequisites

- [DDEV](https://ddev.readthedocs.io/) ≥ 1.25
- [Docker](https://docs.docker.com/get-docker/)
- [Composer](https://getcomposer.org/) 2 (use `ddev composer`)
- [pnpm](https://pnpm.io/) 11 (use `ddev pnpm`)

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/instride-ch/opendxp-skeleton.git my-project
cd my-project
```

### 2. Start DDEV

```bash
ddev start
```

This spins up the web container (PHP 8.4, nginx-fpm), MariaDB 11.8, Redis, and OpenSearch. On first start it also creates a `testing` database and grants permissions automatically.

### 3. Install PHP dependencies

```bash
ddev composer install
```

### 4. Install OpenDXP

```bash
ddev php bin/console opendxp:install --mysql-host-socket=db
```

Follow the interactive prompts to set up the admin user and initial site configuration.

### 5. Install Node dependencies

```bash
ddev pnpm install
```

### 6. Start the frontend dev server

```bash
ddev pnpm dev
```

The site is now available at **https://opendxp-skeleton.ddev.site** and the Vite dev server runs at port 5173 with hot reload.

## Environment Configuration

DDEV injects runtime values (database URL, Redis URL, etc.) automatically via `.env.local` on `ddev start`. The `.env` file holds application defaults — do not put secrets there.

Key environment variables:

| Variable           | Default                      | Purpose                                 |
| ------------------ | ---------------------------- | --------------------------------------- |
| `APP_ENV`          | `dev`                        | Symfony environment (`dev` / `prod`)    |
| `APP_DEBUG`        | `true`                       | Enable Symfony debug mode               |
| `OPENDXP_DEV_MODE` | `false`                      | OpenDXP developer toolbar               |
| `DATABASE_URL`     | injected by DDEV             | Doctrine / MariaDB connection           |
| `REDIS_URL`        | `redis://localhost`          | Cache and session store                 |
| `MAILER_DSN_MAIN`  | `smtp://localhost:1025`      | Primary mail transport (Mailpit in dev) |

For local overrides create `.env.local` (git-ignored):

## Development

### Useful commands

**Frontend**

```bash
ddev pnpm dev        # Vite dev server with hot reload (port 5173)
ddev pnpm build      # TypeScript check + production build → public/build/
ddev pnpm preview    # Preview production build locally
ddev pnpm lint       # Lint with oxlint
ddev pnpm lint:fix   # Auto-fix lint issues
ddev pnpm fmt        # Format with oxfmt
ddev pnpm fmt:check  # Check formatting without writing
```

**Backend**

```bash
ddev php bin/console cache:clear
ddev php bin/console debug:router
ddev php bin/console doctrine:migrations:migrate
ddev php bin/console debug:container
```

**DDEV**

```bash
ddev start           # Start all containers
ddev stop            # Stop containers
ddev ssh             # SSH into the web container
ddev logs            # Tail container logs
ddev adminer         # Open Adminer (database UI)
ddev redis-cli       # Open Redis CLI
```

### Xdebug

Xdebug is installed but disabled by default to avoid the performance hit.

```bash
ddev xdebug          # Enable Xdebug
ddev xdebug off      # Disable Xdebug
```

### Code quality

Linting and formatting run automatically as a pre-commit hook (`.vite-hooks/pre-commit` → `vite staged`). The toolchain uses **oxlint** for JavaScript/TypeScript linting and **oxfmt** for formatting. Configuration files: `.oxlintrc.json` and `.oxfmtrc.json`.

## Production Build

```bash
# Build frontend assets
ddev pnpm build

# Clear caches
ddev php bin/console cache:clear --env=prod

# Run migrations
ddev php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

Full-page caching is enabled automatically in the `prod` environment (see `config/packages/full_page_cache.yaml`). Redis is used for application-level caching in all environments.

## Project Structure

```
├── assets/                 # Frontend source (TypeScript, CSS, images)
│   ├── main.ts             # Vite entry point
│   └── styles.css          # Global styles with design tokens
├── bin/console             # Symfony CLI entry point
├── config/
│   ├── packages/           # Symfony bundle configuration
│   ├── opendxp/            # OpenDXP-specific config (constants, credentials)
│   ├── routes/             # Route definitions
│   └── services.yaml       # Service container configuration
├── public/
│   ├── index.php           # Web entry point
│   └── build/              # Compiled frontend assets (git-ignored)
├── src/
│   ├── Controller/         # Symfony controllers (attribute routing)
│   ├── Command/            # Symfony console commands
│   ├── EventSubscriber/    # Event subscribers
│   └── Kernel.php          # Extends OpenDxpKernel
├── templates/
│   ├── base.html.twig      # Root layout with Vite asset tags
│   ├── base/               # Shared partials (header, footer, favicons)
│   └── default/            # Page-level templates
├── translations/           # Translation files (YAML)
├── var/
│   ├── assets/             # OpenDXP managed assets (upload target)
│   └── classes/DataObject/ # Auto-generated OpenDXP data object classes
├── .ddev/                  # DDEV configuration
├── vite.config.ts          # Vite configuration (Symfony plugin)
└── tsconfig.json           # TypeScript configuration
```

## Additional Services

The DDEV setup includes optional Docker Compose overrides in `.ddev/`:

| File                              | Service    | Purpose                          |
| --------------------------------- | ---------- | -------------------------------- |
| `docker-compose.redis.yaml`       | Redis      | Cache and sessions               |
| `docker-compose.opensearch.yaml`  | OpenSearch | Full-text search                 |
| `docker-compose.adminer.yaml`     | Adminer    | Web-based database management UI |

## License

Proprietary — © instride AG. All rights reserved.
