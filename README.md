# LARP Organization Website

A website and admin panel for organizations that run LARPs, built with **Symfony 7.4 LTS** and replacing the Las Tajemnic Google Site.
The roadmap is tracked in GitHub issues: epic [#2](https://github.com/tbuczen/larp_org_web/issues/2).

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

## Content model

| Entity | What it is |
|---|---|
| `Larp` | A LARP project or another initiative (e.g. a LARP group). Status: draft (hidden), current, past. |
| `LarpEdition` | One run of a LARP: dates, place, recruitment status, registration link. Edited inside the LARP form. |
| `Character` | A character in one edition: casting, availability (available / taken / hidden), portrait, description. |
| `Tag` / `TagCategory` | Shared dictionary of **trigger** and **gameplay** tags, grouped in categories. |
| `CharacterTag` | A tag on a character with a percentage (1–100). |

The LARP page shows the **current edition**: the next one that hasn't ended, otherwise the latest.
In the admin, each character has two lists, *Triggers* and *Gameplay*, that store into the same `CharacterTag` table.

Public pages: `/larpy`, `/larp/{slug}`, `/larp/{slug}/postacie`, `/larp/{slug}/postac/{character}`.
Uploaded images go to `public/uploads/` (not committed; back it up together with the database).

## Project layout

```
src/
  Controller/Admin/   EasyAdmin dashboard + CRUD controllers
  Controller/         Public controllers, login
  Entity/             Doctrine entities
  Enum/               Statuses and types (translated via translations/enums.*.yaml)
  Form/               Custom form types (CharacterTagType)
  Twig/, Util/        Twig filters (edition_dates) and helpers
  Command/            Console commands (app:create-admin)
  DataFixtures/       Demo data
templates/            Twig templates (public templates move into themes/ with the theme system)
translations/         admin.*, enums.*, validators.*, messages+intl-icu.* (public pages), PL + EN
assets/               AssetMapper assets (styles/admin.css holds the admin additions)
migrations/           Portable Doctrine migrations
docs/issues/          Original issue drafts (now on GitHub)
```

## Quality checks

```bash
vendor/bin/phpunit                         # creates a fresh test DB (migrations + fixtures) each run
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpstan analyse
```

CI (`.github/workflows/ci.yml`) runs all of these, with the tests on both SQLite and MySQL 8.
