# Movable building blocks (page builder)

## Description
The front page (and other content pages) are made of **blocks** that admins can add, configure, hide and **reorder by drag and drop**.

## Data model
- `Page`: title, slug, is_home flag, SEO meta
- `PageBlock`: page, type, position, enabled, settings (JSON), optional LARP scope

## Block types (first set)
- Hero (image/video background, headline, CTA buttons)
- LARP showcase (featured or upcoming LARPs as cards)
- Rich text
- Image + text (left/right)
- Gallery teaser
- Character matcher teaser / embed
- Social links
- Call to action banner
- Countdown to the next LARP
- Embed (YouTube, map)

## Architecture (so new blocks are easy to add, including with AI)
- Each block type is a PHP class implementing `BlockTypeInterface` (tag `app.block_type`) that defines: key, label, its settings form type, default settings, and a Twig template `blocks/<key>.html.twig`
- Themes can override any block template (`themes/<theme>/templates/blocks/<key>.html.twig`)

## Admin
- A "Page layout" screen in EasyAdmin with a drag-and-drop sortable list (Stimulus + SortableJS). Order is saved through AJAX (CSRF protected).
- Add block → choose type → settings form generated from the block's form type
- Enable/disable toggle and duplicate action

## Acceptance criteria
- [ ] Reordering by drag and drop is saved and shows on the public page
- [ ] A new block type can be added with one PHP class + one Twig template, with no other wiring
