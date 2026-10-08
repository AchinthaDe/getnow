# 0001: Content API Tie-Break and MaxCount Enforcement

## Status
Accepted

## Context
When querying content blocks for a specific placement via the API, multiple blocks may be published, enabled, and currently in their scheduled window. Each placement has a maximum allowed block count (e.g., 1 for an announcement bar). We need a deterministic way to sort overlapping blocks and enforce this cap.

Enforcing the cap at publish-time using database locks (`max()`) is prone to race conditions, requires complex overlapping window checks, and prevents an admin from scheduling a replacement to take over automatically without archiving the currently live one first.

Furthermore, PostgreSQL sorts `NULL` dates first by default when using `DESC`. A block without a `starts_at` (always on) would therefore beat a scheduled block (which has a `starts_at`), which is counterintuitive.

## Decision
1. **API Output Enforcement**: The `maxCount` constraint is completely removed from the publishing lifecycle. The server allows publishing any number of overlapping blocks. The `maxCount` is strictly enforced via a `limit()` at the API output layer.
2. **Deterministic Tie-Break**: Overlapping blocks are ranked deterministically at the API layer. The exact sort order is:
   1. `sort_order` ASC
   2. `starts_at` DESC NULLS LAST
   3. `id` DESC
3. **One Block = One Message**: For the announcement bar, one block instance represents exactly one message, rather than a single block containing an array of messages.

## Consequences
- **Positive**: Admins can easily schedule a temporary replacement block. If it overlaps with an always-on block, the scheduled one will win while active, and the always-on one will seamlessly resume when the scheduled one expires.
- **Positive**: No complex database overlapping logic or locking is required during the Publish action.
- **Negative**: The admin UI list might show multiple blocks as "Live", which could confuse admins if the placement has a cap of 1. We must add UI clarity (like tooltips or computed indicators) to explain that "Live" means available, but the cap ultimately decides visibility.
