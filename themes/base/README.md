# Base theme

The neutral theme every other theme builds on. **Don't edit it to restyle one
organization's site** — create a child theme instead:

```bash
php bin/console app:theme:create my-org --label="My Org"
```

then choose it in **Admin → Ustawienia strony → Wygląd**.

## How themes work

```
themes/<name>/
  theme.yaml          name, description, extends (parent theme), styles, scripts, preload
  README.md           what this theme is and which files to edit
  templates/          Twig overrides (same path as in the parent theme)
  assets/styles/      CSS files listed in theme.yaml, loaded after the parent's
  assets/scripts/     plain ES modules listed in theme.yaml (no npm imports)
  assets/images/      images; special names below
  assets/fonts/       self-hosted fonts (no Google Fonts requests)
```

- **Templates** are looked up in the active theme, then its parent(s), then `templates/`.
  To change one template, copy it from here into your theme under the same path.
- **CSS/JS**: every theme in the chain contributes, parent first. A child theme usually
  only needs `tokens.css` (+ optionally `components.css` and `effects.css`).
- **Fonts used in the header** can be listed under `preload:` in theme.yaml (paths in
  `assets/`); they're preloaded so the menu doesn't shift when the font arrives.
- **Special images** (found by name, first theme in the chain that has them wins):
  `images/logo.svg` (header logo), `images/favicon.svg`, `images/hero.svg`
  (hero background when a block has no image and features no LARP with a cover).
- Uploaded content images (LARP covers, block images) always take priority over theme art.

## Rules for theme authors (and AI assistants)

1. **Colours, fonts, radii and shadows only through CSS custom properties** from
   `tokens.css`. Never hard-code a colour in a template: LARP colour schemes (#16)
   override the tokens per LARP.
2. **Mobile first.** Check 390 px width; nothing may overflow horizontally. Don't park
   fixed elements off-screen with `translateX` (mobile browsers zoom out to show them).
3. **Respect `prefers-reduced-motion`.** Animations go in `effects.css` / theme scripts;
   the site must look complete without them. Never hide content in a way that needs
   JavaScript to reveal it (add "hidden until revealed" classes from JS only).
4. **No external requests** for fonts, scripts or images (privacy, speed).
5. Keep accessibility: visible focus, alt texts, contrast ≥ 4.5:1 for body text.

## Design tokens (`assets/styles/tokens.css`)

| Token | Used for |
|---|---|
| `--color-bg`, `--color-surface`, `--color-surface-2`, `--color-border` | page, cards, inputs, lines |
| `--color-text`, `--color-muted` | text, secondary text |
| `--color-primary`, `--color-on-primary`, `--color-link` | buttons, links, highlights; text on primary |
| `--color-accent`, `--color-danger` | eyebrows, hover, warnings/triggers |
| `--font-body`, `--font-heading`, `--font-ui`, `--font-brand` | body text, headings, buttons/labels, logo text |
| `--heading-weight`, `--heading-tracking`, `--button-radius`, `--button-tracking` | typographic details |
| `--radius`, `--radius-small`, `--max-width`, `--gutter`, `--header-height` | shape and layout |
| `--shadow`, `--hero-overlay`, `--page-background`, `--header-bg`, `--header-border`, `--cta-bg`, `--footer-bg`, `--block-spacing` | effects and backgrounds |

## Templates

| Template | What it renders |
|---|---|
| `base.html.twig` | `<html>`/`<head>`: theme CSS and JS, meta tags |
| `layout.html.twig` | header (logo, `main` menu, burger on phones), `<main>`, footer (`footer` menu, social links). Blocks: `main` (full width), `content` (inside `.container`) |
| `partials/_menu.html.twig` | a menu from `menu('main')` / `menu('footer')`; submenus use `<details>` |
| `partials/_social.html.twig` | Facebook / Instagram / Discord buttons |
| `partials/_recruitment.html.twig`, `partials/_cover.html.twig` | recruitment badge, LARP cover or placeholder |
| `page/show.html.twig` | a block-based page (also the front page) |
| `blocks/<key>.html.twig` | one page block each: `hero`, `larp_showcase`, `rich_text`, `image_text`, `gallery`, `parallax_banner`, `matcher_teaser`, `social_links`, `cta`, `countdown`, `embed` |
| `larp/index.html.twig`, `larp/show.html.twig`, `larp/_card.html.twig` | LARP list, LARP page (with its galleries), LARP card |
| `gallery/index.html.twig`, `gallery/show.html.twig`, `gallery/_card.html.twig` | gallery list, one gallery (masonry + viewer), gallery card |
| `partials/_lightbox.html.twig` | the full-screen photo viewer (used by the gallery page and the gallery block) |
| `larp/characters.html.twig`, `character/matcher.html.twig`, `components/CharacterMatcher.html.twig` | character matcher pages and the live component |
| `character/show.html.twig`, `character/_card.html.twig`, `character/_bars.html.twig` | character page, card, percentage bars |
| `site/home.html.twig` | fallback front page before one is built in the admin |

Block templates receive `block` (the PageBlock), `settings` (defaults merged in) and the
block type's own variables (see `src/Block/Type/*.php`, method `getContext()`).

## Twig helpers

- `theme()` – the active theme (`theme().name`, `theme().label`)
- `theme_asset('images/hero.svg')` – logical asset path from the theme chain, or null
- `theme_stylesheets()`, `theme_scripts()`, `theme_preloads()` – used by `base.html.twig`
- `menu('main')` – list of links (`label`, `url`, `active`, `newTab`, `children`)
- `render_block(block)`, `edition|edition_dates` ("6–9.VIII.2026")

## Motion (scroll effects, galleries)

Shared Stimulus controllers in `assets/controllers/` do the work; themes only style it:

- `scroll-fx` (`data-controller="scroll-fx"`) sets `--p` (0 → 1 while the element
  crosses the screen), `--pc` (−1 → 1, 0 in the middle) and `--exit` (0 → 1 as it
  leaves the top) on the element, and adds `fx-ready` / `is-inview` classes. Used by
  the hero (picture sinks, text lifts away), `parallax_banner` and `gallery`.
  Example: `.thing { transform: translateY(calc(var(--pc, 0) * 40px)); }`
- `lightbox` — full-screen photo viewer for the gallery block and gallery pages.
- Gallery layouts: `drift` (endless strips), `scroll` (strips move with the page),
  `parallax` (columns at different speeds), `grid` (photos unveil in turn).

With reduced motion the controllers do nothing and the CSS shows still pictures;
moving strips become swipeable rows.

## Adding a new block type

Create `src/Block/Type/MyBlock.php` extending `AbstractBlockType` (key, icon, default
settings, settings form) and `themes/base/templates/blocks/my_block.html.twig`. It
appears in the admin's "Add a block" list automatically. Add its admin labels to
`translations/admin.*.yaml` under `block.my_block`.
