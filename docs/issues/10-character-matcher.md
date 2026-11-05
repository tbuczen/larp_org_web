# Public character matcher: visitors filter characters by preferences (match %)

## Description
Like https://zapisy.krak-on.info/, visitors say which kinds of play they **want** and which they **don't want**, and which **triggers** they want to avoid. The system then ranks characters by **match percentage**.

## How the reference site works (krak-on)
- Visitors rate each "atmosphere" tag on a 5-point scale: *Hate it (-2) / Rather not (-1) / Don't mind (0, default) / Like (+1) / Love (+2)*
- Triggers are checkboxes grouped in collapsible categories. They do not change the score; games containing them are **highlighted** (bold, ⚠).
- Match = `round(((average rating of the game's tags) + 2) / 4 * 100)`, with 50% if there are no tags. Labels: ≥80 "Great match", ≥60 "Good match", ≥40 "Could work", otherwise "Probably not for you".
- The list is sorted by match. The match bar is one hue that gets more intense with the score (colorblind-safe, not a red→green gradient).
- Filter by character type (female / male / non-binary).

## Our version (improvement: weight by the character's tag %)
- Visitor input:
  - gameplay tags rated with the same 5-point scale (or a simpler 3-state want / neutral / don't want)
  - trigger checkboxes grouped by category
  - optional filters: LARP, character gender, show only available characters
- Score for each character:
  `score = Σ(rating_t × pct_t) / Σ(pct_t)` over the character's gameplay tags → normalized `match% = round((score + 2) / 4 × 100)`
- Trigger handling: characters whose triggers include the visitor's marked triggers get a ⚠ badge listing them (with the character's % for each). A toggle **"hide characters with my triggers"** removes them instead.
- Show "things you said you don't like" (tags rated -2) on each card, as krak-on does.

## Implementation
- **Symfony UX Live Component** `CharacterMatcher`: re-renders the results live as the visitor clicks, with no custom JS API needed. The scoring lives in a plain PHP service `CharacterMatchCalculator` (unit-tested).
- Preferences are kept in the URL query string (shareable link) and in localStorage
- Works on mobile; accessible (radio groups with labels)

## Acceptance criteria
- [ ] Results update immediately on input change
- [ ] Ranking and percentages match the formula (unit tests)
- [ ] Trigger matches are highlighted, and can optionally be hidden
- [ ] Works for a single LARP (`/larp/{slug}/characters`) and across all open LARPs (`/characters`)
