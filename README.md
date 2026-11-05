# LARP Organization Website

A website and admin panel for organizations that run LARPs, built with **Symfony 7.4 LTS** and replacing the Las Tajemnic Google Site.
The feature roadmap is in [`docs/issues/`](docs/issues/).

| Part | Technology |
|---|---|
| Backend | PHP 8.3, Symfony 7.4 LTS, Doctrine ORM 3 |
| Database | SQLite (default) or MySQL / MariaDB |
| Admin panel | [EasyAdmin 5](https://symfony.com/bundles/EasyAdminBundle/current/index.html), with light/dark/auto mode |
| Frontend | Twig, Symfony UX (Stimulus, Turbo, Twig & Live Components), AssetMapper (no Node build step) |

## Quick start (SQLite)

Requirements: PHP 8.3+ with `intl`, `pdo_sqlite` (or `pdo_mysql`), and Composer.

```bash
composer install
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n      # demo data (dev only!)
php -S 127.0.0.1:8000 -t public bin/dev-router.php   # or: symfony serve
```

- Website: http://127.0.0.1:8000/
- Admin: http://127.0.0.1:8000/admin
- Demo logins from the fixtures: `admin@example.com` / `admin1234` (admin) and `editor@example.com` / `admin1234` (editor)

## Creating the first admin (production)

```bash
php bin/console app:create-admin you@example.org
```

Running the command again for an existing e-mail resets that user's password. Add `--editor` to create an editor instead.

## Choosing the database

Put the connection in `.env.local`, which is not committed:

```dotenv
# SQLite (default): a file in var/
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_%kernel.environment%.db"

# MySQL / MariaDB: set serverVersion to your server's version
DATABASE_URL="mysql://user:pass@127.0.0.1:3306/larp?serverVersion=8.0.32&charset=utf8mb4"
DATABASE_URL="mysql://user:pass@127.0.0.1:3306/larp?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
```

Then run `php bin/console doctrine:migrations:migrate -n`.
Migrations use Doctrine's Schema API rather than raw SQL, so the same migrations work on both databases. **Write new migrations the same way:** after `make:migration`, replace the generated SQL with Schema API calls (see `migrations/`).

## Admin panel

- Everything is managed from one EasyAdmin dashboard (`src/Controller/Admin/DashboardController.php`). New sections are added in `configureMenuItems()`.
- **Light / dark / auto** mode is in the user menu (top right) and is remembered in the browser.
- Roles: `ROLE_EDITOR` manages content; `ROLE_ADMIN` can also manage users.
- The admin UI is in Polish and English (`translations/admin.*.yaml`), switchable from the user menu.
- Login is protected with CSRF and throttling (5 attempts per minute).

## Project layout

```
src/
  Controller/Admin/   EasyAdmin dashboard + CRUD controllers
  Controller/         Public controllers, login
  Entity/             Doctrine entities
  Command/            Console commands (app:create-admin)
  DataFixtures/       Demo data
templates/            Twig templates (public templates move into themes/ with the theme system)
translations/         admin.{pl,en}.yaml, validators.{pl,en}.yaml
assets/               AssetMapper assets (styles/admin.css holds the admin additions)
migrations/           Portable Doctrine migrations
docs/issues/          Feature specifications / roadmap
```

## Quality checks

```bash
vendor/bin/phpunit                         # creates a fresh test DB (migrations + fixtures) each run
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpstan analyse
```

CI (`.github/workflows/ci.yml`) runs all of these, with the tests on both SQLite and MySQL 8.
