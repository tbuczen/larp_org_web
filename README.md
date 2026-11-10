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

Requirements: PHP 8.3+ with `intl`, `pdo_sqlite` (or `pdo_mysql`), `gd` (gallery thumbnails; `exif` recommended, to turn phone photos upright), and Composer.

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
In the admin, characters are managed **per LARP**: *Postacie* first asks which LARP, and its list,
"Dodaj postać" and the edition field all stay within that LARP (new characters start in its
current edition). Each LARP row and edit page also has *Postacie* / *Dodaj postać* buttons.

**Tags per LARP.** Besides the tags shared by all LARPs (*Tagi*), each LARP can have its own
(*Tagi larpa*: paste a list, one per line, optionally `Kategoria: Tag`). A character's form offers
the shared tags plus its LARP's own; a LARP tag can't reuse a shared tag's name.

**CSV import** (*Import CSV* on a LARP): upload a file exported from Excel / LibreOffice / Google
Sheets (`;` `,` or tab; UTF-8 or Windows-1250), say which column holds what (any column names;
known ones like `nazwa`, `zajawka`, `rozgrywka`, `triggery` are pre-selected, choices for other
names are remembered; files without a header row work too), check the preview (new / updated /
skipped / errors, new tags), then confirm — all rows are saved in one transaction, or none. Only
the character name is required; tag cells look like `Polityka / intryga 50%; Magia 30%`.
*Pobierz szablon CSV* gives a ready-to-fill file. Code: `src/Import/`, `src/Tagging/TagResolver.php`.
In the admin, each character has two lists, *Triggers* and *Gameplay*, that store into the same `CharacterTag` table.

Public pages: `/larpy` (every edition as its own event card: upcoming ones soonest first; past
ones hidden until "Wszystkie, także minione" / `?minione=1`; initiatives below), `/larp/{slug}` (the LARP with its current edition and cards for all
editions), `/larp/{slug}/{edition}` (one edition: dates, characters, galleries; past ones too),
`/larp/{slug}/{edition}/postac/{character}`, and the character matcher at `/postacie` (all current
LARPs), `/larp/{slug}/postacie` (current edition) and `/larp/{slug}/{edition}/postacie`. Edition
addresses come from their names ("3. edycja" → `3-edycja`) and can be changed in the edition form.
Each edition is treated as an event of its own (*Admin → Edycje*, or "Edycje" on a LARP): its
own dates, place, sign-ups, Facebook event, image, teaser, description, player count and price
(empty texts and image fall back to the LARP's), its own characters and galleries. The LARP is
what connects them. Tags belong to the LARP (or are shared by all LARPs), so every edition uses
the same lists. Creating a LARP leads straight to adding its first edition.

### Character matcher

Visitors rate gameplay tags from −2 (hate it) to +2 (love it) and tick triggers to avoid
(`src/Twig/Components/CharacterMatcher.php`, a Symfony UX Live Component). Scoring lives in
`src/Matcher/CharacterMatchCalculator.php`:

```
score  = Σ(rating × tag %) / Σ(tag %)    over the character's gameplay tags (unrated = 0)
match% = round((score + 2) / 4 × 100)    no gameplay tags → 50%
```

Triggers never change the score: matching characters get a ⚠ warning, or are hidden with
"Ukryj postacie z moimi triggerami". Choices live in the URL (shareable) and are remembered in
localStorage for the next visit.
### Social links and Facebook events

Site settings hold the organization's Facebook, Instagram and Discord. Each LARP can have its
own links (*Larp → Social media*): any number of rows {network, address, optional own label,
e.g. "Grupa graczy"}, stored as JSON so new networks are one enum case in
`src/Social/SocialNetwork.php` (Facebook, Instagram, Discord, YouTube, TikTok, website).
Addresses must be https and on the chosen network's site. The LARP page shows the LARP's links
("Śledź larpa"), or the organization's when it has none; buttons render with the
`<twig:SocialLinks :links="…"/>` component.

Each edition can have a Facebook event (`facebook.com/events/…` or `fb.me/e/…`): the LARP
page gets a "Wydarzenie na Facebooku" button and LARP cards a small Facebook icon. Upcoming
editions also offer "Dodaj do kalendarza", an `.ics` file at `/larp/{slug}/kalendarz.ics`.

### LARP colour schemes

*Larp → Kolory*: pick a preset (Dark fantasy, Mroźna północ, Cyberpunk neon, Postapo rdza,
Historyczny pergamin) and/or set own colours (background, text, primary, secondary, accent;
empty = the preset's, or the theme's). The LARP's pages and its cards then use them: the
colours override the theme's tokens (`src/Theme/LarpColors.php`), so this works with any
theme. The form has colour pickers and a live preview with WCAG contrast ratios; saving
colours that are hard to read shows a warning. Clearing everything returns to the theme.

### Photo galleries

*Admin → Galerie zdjęć*. A gallery usually **links to an album elsewhere** (Google Photos,
Facebook or any other site) and has a cover picture uploaded here; it belongs to a LARP and
optionally one of its editions (or neither, for a workshop or an initiative), with the
photographer ("Zdjęcia: …"), a date and a description. On the site its card shows where the
album is ("Google Photos", "Facebook", or the site's name) and opens it in a new tab;
`/galeria/{slug}` forwards there too.

Photos can **also** be uploaded here (the gallery's *Zdjęcia* screen) — a selection next to the
full album, or a whole gallery for groups that keep photos on the site. Drag in any number at
once: the browser shrinks big ones to web size and sends them one per request, so server upload
limits don't matter. Photos are dragged into order (the first is the cover unless a cover picture
is set), get captions and their own photographer credit, and can be deleted. The server caps
photos at 2560 px and makes 720 px WebP thumbnails with GD (`src/Gallery/`). A gallery with
photos gets its own page (masonry grid, lazy-loaded thumbnails, full-screen viewer) with a
button to the full album.

Public pages: `/galerie`, `/galeria/{slug}`; each LARP page lists its published galleries.
A menu item can link to *Galerie zdjęć*.

Uploaded images go to `public/uploads/` (not committed; back it up together with the database).
For big galleries, give PHP a reasonable `upload_max_filesize` (e.g. 16M); the uploader adapts to
whatever the server allows.

## Themes, pages and menus

- **Themes** live in `themes/<name>/` and are chosen in *Admin → Ustawienia strony → Wygląd*.
  `base` is the neutral theme every other theme extends; `lastajemnic` is the Las Tajemnic
  organization's own theme. Start a new organization's look with
  `php bin/console app:theme:create my-org` and read `themes/base/README.md` (templates,
  tokens and rules, written so an AI assistant can work from it too).
- **Pages** (*Admin → Strony i układ*) are built from blocks: hero, LARP cards, text,
  image + text, photo gallery (drifting strips, parallax or grid, with a full-screen viewer),
  parallax photo banner, matcher invitation, social/contact, call to action, countdown,
  video/map.
  The page marked *Strona główna* is the front page; others are served at `/{slug}`.
  On a page's *Układ* screen blocks are added, dragged into order (or moved with arrows on a
  phone), hidden, duplicated and edited. A new block type is one class in
  `src/Block/Type/` plus `themes/base/templates/blocks/<key>.html.twig`.
- **Menus** (*Admin → Menu nawigacji*): `main` (header) and `footer`. Items link to a page,
  a LARP, a built-in page or a URL, with one level of dropdowns.

## Project layout

```
src/
  Controller/Admin/   EasyAdmin dashboard + CRUD controllers
  Controller/         Public controllers, login
  Entity/             Doctrine entities
  Enum/               Statuses and types (translated via translations/enums.*.yaml)
  Form/               Custom form types (CharacterTagType)
  Twig/, Util/        Twig filters (edition_dates) and helpers
  Theme/              Theme discovery and template loader
  Block/              Page block types and renderer
  Menu/               Menu builder (menu() Twig function)
  Matcher/            Character match calculator
  Command/            Console commands (app:create-admin)
  DataFixtures/       Demo data
templates/            Admin templates (public templates live in themes/)
themes/               base and lastajemnic themes (templates, CSS, JS, images, fonts)
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
