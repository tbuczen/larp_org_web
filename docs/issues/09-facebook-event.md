# Facebook event for each LARP

## Description
A LARP can be linked to a Facebook event.

## Details
- Optional `facebookEventUrl` field on `Larp` (validated to be a facebook.com/events/... URL)
- The LARP page shows a clear "Facebook event" call-to-action button, plus "Add to calendar" (.ics generated from the LARP dates) as a nice to have
- LARP cards on the front page/list show a small Facebook event icon when an event is set
- No Facebook API integration or embed required at first (embeds need app review and tracking consent)

## Acceptance criteria
- [ ] The event button appears only when a URL is set and opens in a new tab
