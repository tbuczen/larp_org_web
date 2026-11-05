# Color scheme for each LARP

## Description
Each LARP can have its own color scheme, applied on that LARP's pages (and on its card on the front page).

## Details
- Fields on `Larp`: primary, secondary, accent, background, text color (color pickers in the admin), plus an optional dark/light preference
- Optional **presets** to choose from (e.g. "Dark fantasy", "Cyberpunk neon", "Post-apo rust", "Historical parchment")
- Rendered as CSS custom properties that override the theme tokens (`<body style="--color-primary: …">` or a scoped `<style>` block). This works with every theme because themes use tokens only (#13).
- Contrast check in the admin: warn when text and background contrast is below WCAG AA
- Empty fields fall back to the organization theme

## Acceptance criteria
- [ ] The LARP page and its card use the LARP's colors
- [ ] Removing the colors reverts to the theme defaults
