# [Epic] LARP organization website + admin panel (Symfony)

## Goal
**Replace the current Las Tajemnic site (https://sites.google.com/lastajemnic.org/lastajemnic)**, which is outdated and unattractive, with a custom website for organizations that run LARPs (no WordPress). It has a clean, unified admin panel and an attractive public front page with a theme for each organization.

## Tech constraints
- **Backend:** PHP 8.3, Symfony 7.4 LTS, using existing bundles where possible
- **Database:** SQLite or MySQL through Doctrine ORM. Switching is one change to `DATABASE_URL`, and no SQL specific to one database is allowed.
- **Frontend:** Twig + Symfony UX (Stimulus, Turbo, Live Components), AssetMapper (no Node build step)
- **Admin:** EasyAdminBundle 4, which comes with a unified CRUD UI and light/dark mode

## Sub-issues
- [ ] 01 Project foundation
- [ ] 02 Admin panel: unified, light/dark theme, authentication
- [ ] 03 LARP management
- [ ] 04 Characters per LARP
- [ ] 05 Tag dictionary + trigger tags per character (with %)
- [ ] 06 Gameplay tags per character (with %)
- [ ] 07 Photo galleries from past LARPs
- [ ] 08 Social media buttons (Facebook / Instagram / Discord): site and each LARP
- [ ] 09 Facebook event for each LARP
- [ ] 10 Public character matcher
- [ ] 11 Movable building blocks (page builder)
- [ ] 12 Editable menus
- [ ] 13 Organization theme system + base theme
- [ ] 14 Color scheme for each LARP
- [ ] 15 Attractive front page with promo imagery
- [ ] 16 Content migration from the Google Sites site + Contact page + go-live on lastajemnic.org
