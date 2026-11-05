# Characters per LARP

## Description
Every LARP has a list of characters that visitors can browse and filter.

## Fields (entity `Character`)
- LARP (ManyToOne), name, slug
- short description / teaser, full description (rich text)
- portrait image (optional)
- character gender / casting option: female / male / non-binary / any. Krak-on lets visitors filter on this, so it should be filterable too.
- availability: available / taken / hidden
- trigger tags with percentage (#05)
- gameplay tags with percentage (#06)
- sort position

## Admin
- Character CRUD with a LARP filter, and a "Characters" action on each LARP row
- Trigger and gameplay tags are edited inline on the character form as collections of rows (tag select + 0–100% slider or number)

## Acceptance criteria
- [ ] Characters can be created, edited and reordered for each LARP
- [ ] Characters marked taken are still shown publicly, labelled "taken"; hidden characters are not shown
