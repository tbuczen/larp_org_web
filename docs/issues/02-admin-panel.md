# Admin panel: unified look, light/dark theme, authentication

## Description
One consistent admin panel for all content, built on **EasyAdminBundle 4**, so the CRUD screens don't have to be hand-built.

## Scope
- `/admin` dashboard with sidebar sections: Site (settings, menus, page blocks, theme), LARPs, Characters, Tags, Galleries
- **Light/dark theme:** EasyAdmin's built-in color scheme switcher (light / dark / auto, following the OS setting), remembered for each user
- Admin styling stays the same for every organization. Organization themes only affect the public site.
- Login form (Symfony Security). `User` entity with roles `ROLE_ADMIN` and `ROLE_EDITOR`.
- `bin/console app:create-admin` command
- Rich text fields use the Trix or TinyMCE editor field available in EasyAdmin
- Translations ready (PL + EN). Admin UI strings go in translation files.

## Acceptance criteria
- [ ] All admin screens share one layout and style
- [ ] Light/dark toggle works and is remembered
- [ ] Anonymous users cannot reach `/admin`
