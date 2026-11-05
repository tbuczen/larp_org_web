# LARP management

## Description
Admins can add and edit LARPs.

## Fields (entity `Larp`)
- name, slug (auto-generated), short teaser, full description (rich text)
- **project type:** LARP / other initiative (for example a LARP group such as "Wyprawa Rycerska Talabeclandu", which has no characters or dates but does have a gallery and social links)
- **format/genre** label, e.g. "Chamber larp", "Field larp" (*larp terenowy*), and inspiration/setting (e.g. "Shadowrun", "Skyrim", "Fallout")
- status: draft / announced / registration open / sold out / past
- **recruitment badge** shown on cards: "Recruitment open!" (links to the registration/Facebook event) or "Recruitment closed"

## Editions (entity `LarpEdition`)
The LARPs on the current site run over several editions (Vault 47: 3 editions 2023–2025; Pradawne Zwoje: 3rd edition in 2026). So:
- `LarpEdition`: LARP, edition number/name, start and end date, location, status, registration link, Facebook event, its own characters and galleries
- The LARP card shows "3 editions · 2023–2025" for past LARPs and the upcoming edition's dates and place for current ones
- Characters (#04) and galleries (#07) belong to an edition, which defaults to the LARP's current edition
- start date and end date, location (text + optional map link)
- hero/cover image, optional promo images
- player count and price (optional, free text)
- registration link (external URL, optional)
- social links, Facebook event link (see #08 and #09)
- color scheme (see #14)
- position/sort order and a "featured on front page" flag

## Public side
- `/larps` lists LARPs in sections, as on the current site: **Currently organized LARPs**, **Previous LARP projects**, **Other initiatives**
- `/larp/{slug}` is the LARP page: hero, description, dates, characters link, gallery, social buttons, Facebook event button

## Acceptance criteria
- [ ] CRUD in the admin, with image upload
- [ ] Draft LARPs are hidden on the public site
- [ ] The LARP page uses that LARP's color scheme
