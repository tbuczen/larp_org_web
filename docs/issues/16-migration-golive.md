# Content migration from Google Sites, Contact page, go-live on lastajemnic.org

## Description
The goal of the project is to replace https://sites.google.com/lastajemnic.org/lastajemnic. Every piece of content on it needs to move to the new site, and the domain needs to point at the new site.

## Current site inventory
**Menu:** Home · Association (*Stowarzyszenie*) · Our LARPs ▾ (CyberCrush, Pradawne Zwoje, Vault 47) · Other initiatives ▾ (Wyprawa Rycerska Talabeclandu) · Contact

**Current LARPs (2026)**
- **Cybercrush**: 17–18.VII.2026, Dąbrowa Górnicza. Chamber larp inspired by Shadowrun. Recruitment closed.
- **Pradawne Zwoje**: 6–9.VIII.2026, Czyżowice. Field larp in the Skyrim world, 3rd edition. Recruitment open (Facebook event https://fb.me/e/69mffUNUK).

**Previous LARPs**
- **Vault 47**: 3 editions, 2023–2025. Fallout world, the story of Vault 47's residents and the GECK. Photos by: Rekografia.
- **Czasy Pogardy**: 1 edition, 2023. Inspired by the Witcher world. Photos by: Klaudia Mikulec.

**Other initiatives**
- **Wyprawa Rycerska Talabeclandu**: LARP group travelling to field and battle LARPs in the Warhammer Fantasy world. Photos by: Rekografia and Klaudia Mikulec.

**Contact / social**
- E-mail: stowarzyszenie@lastajemnic.org
- Discord Las Tajemnic (main): discord.gg/Zjf2mZN5jV and discord.gg/c5UZ7nK6cE. Check which invite is current.
- Facebook: Las Tajemnic (StowarzyszenieLasTajemnic), Pradawne Zwoje (pradawnezwoje), Larp Cybercrush (profile id 61586144905657), Larp Vault 47 (larpvault47), Wyprawa Rycerska (WyprawaRycerskaTalabeclandu)
- Front page tagline: "Przeżyj prawdziwą przygodę — Dołącz do naszej społeczności i śledź postępy nad naszymi projektami" ("Live a real adventure. Join our community and follow the progress of our projects")

## Tasks
- [ ] A fixture/import command (`app:import:las-tajemnic`) that creates the content above: LARPs, editions, social links, menu, home page blocks
- [ ] Download the original photos from Google Sites/Drive (full resolution) and import them into galleries with photographer credits
- [ ] **Contact page:** e-mail (spam-protected), social links, and an optional contact form (Symfony Mailer + honeypot/rate limit) as a "Contact" page block
- [ ] "Association" (*Stowarzyszenie*) page: about the organization, statute/legal info
- [ ] A **Las Tajemnic theme** (`themes/lastajemnic/`, extends base) with logo and forest/mystery visuals
- [ ] Hosting + deployment docs (shared PHP hosting compatible with SQLite or MySQL; deploy script)
- [ ] Point the lastajemnic.org domain at the new site (DNS) and set up HTTPS
- [ ] Update links in the Facebook pages and Discord to the new domain

## Acceptance criteria
- [ ] All content and photos from the old site are on the new site
- [ ] The new site is live on lastajemnic.org, and the Google Site is unpublished or redirects
