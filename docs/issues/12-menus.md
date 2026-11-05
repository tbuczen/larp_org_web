# Editable menus

## Description
Admins can edit the site menus (header and footer).

## Data model
- `Menu`: key (`main`, `footer`), name
- `MenuItem`: menu, parent (for 1 level of dropdowns), label, target type (`page` | `larp` | `route` | `url`), target reference, open in new tab, position, enabled

## Admin
- Menu editor with drag-and-drop ordering and nesting (same Stimulus + SortableJS approach as #11)
- Choosing a page or LARP links to it by entity, so changing a slug doesn't break the link

## Public
- Twig component `<twig:Menu key="main"/>`, rendered by the theme (themes control the markup and style)
- The active item is highlighted; a mobile burger menu

## Acceptance criteria
- [ ] Menu changes appear on the public site immediately (cache invalidated on save)
- [ ] Dropdown submenus work on desktop and mobile
