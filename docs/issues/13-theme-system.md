# Organization theme system + base theme (structured so AI can easily modify it)

## Description
Each organization using this software can have its own theme ("motive"). The software ships with a clean **base** theme. Themes need a clear, predictable structure so that someone (possibly with AI help) can add fancy graphics and effects to the front page without touching application code.

## Theme structure
```
themes/
  base/
    theme.yaml            # name, description, author, parent theme, default colors, fonts, which blocks it styles
    README.md             # guide for humans and AI: which files to edit for what
    templates/            # Twig overrides: layout.html.twig, partials/, blocks/, larp/, character/
    assets/
      styles/
        tokens.css        # CSS custom properties only (colors, fonts, spacing, radii, shadows)
        base.css          # typography, layout
        components.css    # cards, buttons, menu
        effects.css       # optional animations, parallax, particles, etc.
      controllers/        # optional Stimulus controllers for effects
      images/
  my-org/                 # e.g. extends: base, overrides only what it needs
```

## Mechanics
- The active theme is chosen in Site settings (admin), with a preview option
- Template resolution: `themes/<active>/templates` → `themes/<parent>/templates` → `templates/` (registered Twig loader paths)
- Theme assets are served through AssetMapper paths
- **Design tokens only through CSS custom properties.** Templates never hard-code colors, which lets the LARP color scheme (#14) override them.
- `bin/console app:theme:create <name> --extends=base` scaffolds a new theme
- The theme README documents every template and block hook, so an AI assistant can be pointed at one folder

## Acceptance criteria
- [ ] Switching themes in the admin changes the public site without code changes
- [ ] A child theme can override a single template or CSS file
- [ ] The base theme looks clean and modern by itself
