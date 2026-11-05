# Tag dictionary + trigger tags per character (with percentage)

## Description
Each character lists the **triggers** (sensitive content) it involves. Each trigger has a percentage showing how strongly or often it appears for that character.

## Data model
- `Tag`: name, slug, type (`trigger` | `gameplay`), optional `TagCategory` (for example "Death and grief", "Violence", "Mental health") for grouping, optional description
- `CharacterTag`: character, tag, percentage (0–100)
- Tags are shared across all LARPs, so visitors' preferences work across every game

## Admin
- Tag dictionary CRUD, filterable by type and category
- On the character form, a collection of {trigger tag, %} rows; the same tag cannot be added twice
- Creating a missing tag directly from the character form is a nice to have

## Public
- The character card/page lists its triggers with percentages (for example a small bar)
- Triggers the visitor marked are highlighted with ⚠ in the matcher (#10)

## Acceptance criteria
- [ ] Triggers with % are stored and shown for each character
- [ ] Tags are grouped by category in the admin and in the visitor filter
