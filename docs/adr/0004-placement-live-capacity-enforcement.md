# 0004: Placement Live Capacity Enforcement

Date: 2026-10-16

## Status
Accepted (Amends ADR 0001 and Decision D8)

## Context
ADR 0001 (Decision D8) originally removed write-time capacity enforcement, allowing administrators to publish or enable any number of overlapping blocks and relying solely on the API query layer (`limit()` with `sort_order ASC, starts_at DESC NULLS LAST, id DESC`) to tie-break and cap the live blocks.

In practice, this led to substantial administrative confusion:
1. Admins could publish multiple blocks in a singleton placement (e.g., `announcement_bar`), with both marked as "Published" and "Enabled", but only one actually appearing on the storefront. The other block was silently shadowed without warning.
2. Admins had no clear feedback during creation or editing as to whether activating a block would exceed placement capacity.
3. Race conditions could occur if two admins simultaneously published competing blocks.

## Decision
1. **Write-Time Capacity Enforcement**:
   We amend Decision D8 by introducing write-time live capacity enforcement. Activating a block (via `PublishContentBlock`, `EnableContentBlock`, or schedule-changing `UpdateContentBlock`) validates instantaneous schedule concurrency against `Placement::maxCount()`. If activating the block would cause concurrent live blocks to exceed `maxCount()` at any point in its active window, the operation throws a domain `PlacementCapacityExceededException`.
2. **PostgreSQL Advisory Locking**:
   To prevent concurrent race conditions across administrative transactions targeting the same placement, each mutating Action serializes on a transaction-level PostgreSQL advisory lock (`pg_advisory_xact_lock`) before acquiring any row-level locks. The lock key is derived deterministically from the placement string (`(int) (crc32('placement:' . $placementValue) & 0x7FFFFFFF)`).
3. **Legacy Data & Read-Side Safety Net**:
   D8 deterministic tie-breaking (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`) and `maxCount()` slicing are retained in `GetLayoutContent` and `GetBlockServingStatus` as a read-side fallback for legacy over-capacity data. Non-schedule edits (e.g. text/payload updates) on over-capacity legacy blocks are permitted to prevent locking admins out of existing content.
4. **UI Clarity & Domain Guidance**:
   Filament forms display live capacity hints powered by the invokable domain action `CountLiveBlocksForPlacement`, giving administrators immediate visibility into available capacity before publishing or enabling.

## Consequences
- **Positive**: Eliminates administrative ambiguity: admins cannot inadvertently publish shadowed blocks in singleton placements.
- **Positive**: Prevents race conditions during simultaneous publish/enable actions via placement advisory locks without table-wide locking.
- **Positive**: Maintains full backward compatibility with legacy over-capacity datasets via retained D8 tie-breaking on the read side.
- **Negative**: Admins wishing to replace an active singleton block must either schedule the new block for a future non-overlapping window or archive/disable the existing block prior to activating the new one.
