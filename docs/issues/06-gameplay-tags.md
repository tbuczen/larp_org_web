# Gameplay tags per character (with percentage)

## Description
Each character describes its **gameplay** (for example Drama, Intrigue / politics, Combat, Romance, Mystery / investigation, Dance / movement, Horror) as tags with a percentage showing how much of the character's play is that kind of gameplay.

## Details
- Uses the same `Tag` / `CharacterTag` model as #05, with `type = gameplay`
- On the character form, a separate collection from triggers: {gameplay tag, %}
- A hint in the admin that gameplay percentages *may* add up to about 100%, without enforcing it
- Shown on the public character card as tag chips or bars ordered by %

## Acceptance criteria
- [ ] Gameplay tags with % can be edited for each character and are shown publicly
- [ ] The matcher (#10) uses the gameplay percentages as weights
