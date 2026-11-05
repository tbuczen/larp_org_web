# Attractive front page with promo imagery

## Description
The front page should look great: big promo images of LARPs, a clear path to upcoming games, and the character matcher.

## Default layout (built from blocks, #11)
1. Full-width hero: promo image or video of the next or featured LARP, with title, dates and CTAs ("See characters", "Facebook event")
2. Upcoming LARPs: image cards using each LARP's color scheme (#14)
3. "Find your character" matcher teaser (#10)
4. "From past games": gallery mosaic (#07)
5. About the organization + social links (#08)

## Requirements
- Responsive images (`srcset`, WebP through LiipImagine), lazy loading below the fold
- Good performance (Lighthouse ≥ 90 on mobile for the base theme)
- SEO: meta tags, Open Graph image for each LARP (for Facebook sharing), sitemap.xml
- Accessible (contrast, alt texts required on promo images in the admin)

## Acceptance criteria
- [ ] Fresh install + fixtures show a good-looking front page in the base theme
- [ ] All front page sections are editable and reorderable in the admin
