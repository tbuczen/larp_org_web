# Las Tajemnic theme

Theme of **Stowarzyszenie Larpowe „Las Tajemnic”** only. Other groups using this
project should create their own theme (`php bin/console app:theme:create <name>`)
instead of editing this one. Extends `base` — read `themes/base/README.md` first.

## Concept: ink & bone

Everything comes from the association's logo: a dryad whose antlers grow into
branches, cut like a black-and-white woodcut, inside a square frame, above squared
"LAS TAJEMNIC" lettering.

- **Palette:** near-black ink (`#080808`) and warm bone (`#ece6d8`). The only
  extra colour is a muted moss green accent (`#9fb38a`), used for eyebrows and
  small details.
- **Shapes:** square corners everywhere (`--radius: 0`). Cards, CTAs and focus
  states use the logo's double frame: an ink gap, then a thin line
  (`--frame`, `--frame-active`).
- **Type:** headings, buttons and menus use *Saira Extra Condensed*, in uppercase
  with wide tracking, close to the squared logo lettering and with full Polish
  glyphs. Body text uses *Inter*.
- **Texture:** paper grain over the hero, slow fog, a few drifting bone and moss
  motes. All of it stops when the visitor prefers reduced motion.

The look works for all the group's genres (fantasy, futuristic, post-apo): it's
the forest's look, not any one world's. Per-LARP colours (#16) are where each
game gets its own accent.

## Files

| File | What to change there |
|---|---|
| `assets/styles/tokens.css` | palette, fonts, frame — start here |
| `assets/styles/fonts.css` | `@font-face` for the self-hosted fonts in `assets/fonts/` (SIL OFL, see `LICENSE.md`) |
| `assets/styles/components.css` | brand, buttons, framed cards, headings, badges, tag bars, footer |
| `assets/styles/effects.css` | paper grain, motes, reveal-on-scroll |
| `assets/scripts/effects.js` | adds motes to heroes; reveal on scroll |
| `templates/partials/_brand.html.twig` | header brand: emblem + tagline + wordmark (overrides base) |
| `assets/images/logo.svg`, `favicon.svg` | emblem only (dryad in frame, bone on ink) — header, favicon |
| `assets/images/logo-full.svg` | emblem + lettering, for dark backgrounds — footer |
| `assets/images/logo-mono.svg` | one-colour black original, for print and light backgrounds |
| `assets/images/logo-wordmark.svg` | "LAS TAJEMNIC" lettering alone, bone |
| `assets/images/logo-original.jpg` | the source image the SVGs were traced from |
| `assets/images/hero.svg` | default hero: the dryad's branches fading out of the dark |
| `assets/images/card-placeholder.svg` | cover for LARPs without an uploaded image |

The logo SVGs were vector-traced (potrace) from `logo-original.jpg`. To swap the
logo, replace those files and keep the same names; the base layout picks up
`logo.svg` and, if present, `logo-full.svg`.

## Using real photos

The illustration is only a fallback. Upload a photo in the hero block's settings (or a
cover on the LARP the hero features) and it replaces the illustration; the dark
gradient overlay keeps the headline readable. Black-and-white or desaturated photos
suit the theme best. Recommended: landscape, ≥ 1920 px wide, subject on the right
(the text sits on the left).

## Ideas for later

- Per-LARP accent (#16): keep ink and bone, change only `--color-accent` per game.
- A short looping hero video (muted, greyscale, with a poster image).
