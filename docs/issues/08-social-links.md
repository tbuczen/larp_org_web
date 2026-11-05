# Social media buttons: Facebook, Instagram, Discord (site-wide and for each LARP)

## Description
Social buttons for the organization as a whole and for each LARP.

## Details
- Site settings: Facebook page URL, Instagram URL, Discord invite URL
- Each LARP: its own Facebook / Instagram / Discord URLs (all optional)
- Display: site-wide buttons in the header/footer and as a "Social" page block (#11). The LARP page shows that LARP's links, or falls back to the organization's.
- Icons from `symfony/ux-icons` (Simple Icons set), styled by the active theme
- Implemented as a reusable Twig component `<twig:SocialLinks :links="..."/>`
- Built to allow more networks later (YouTube, TikTok) without schema changes. For example, store as an embeddable or a JSON list of {network, url}.

## Acceptance criteria
- [ ] Buttons appear only when a URL is set
- [ ] URLs are validated (must be https)
