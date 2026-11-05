# Photo galleries from past LARPs

## Description
Admins upload photo libraries from past LARPs, and visitors can browse them.

## Data model
- `Gallery`: title, **photographer credit** (shown as "Photos by: …", as on the current site), optional LARP (ManyToOne, so a gallery can also be standalone), event date, description, cover photo, published flag
- `Photo`: gallery, image file, caption, photographer credit, position

## Admin
- Gallery CRUD with **multi-file upload** (drag and drop many photos at once)
- Photos can be reordered, and captions and credits edited
- Thumbnails generated automatically (LiipImagine)

## Public
- `/galleries` overview, `/gallery/{slug}` grid with lightbox (a Stimulus controller wrapping a lightweight lightbox library such as GLightbox or PhotoSwipe)
- The LARP page shows its galleries
- Lazy loading and responsive image sizes

## Acceptance criteria
- [ ] 100+ photos can be uploaded in one go
- [ ] Gallery pages load fast (thumbnails, lazy loading)
