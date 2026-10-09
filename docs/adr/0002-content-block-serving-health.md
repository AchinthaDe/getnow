# 0002: Content Block Serving Health

Date: 2026-10-09

## Status
Accepted

## Context
When serving content blocks via the public API, we need to ensure that the content is safe to display. A block is considered "live" by lifecycle rules if it is published, enabled, and within its scheduling window. However, even if a block is live, its payload might be unservable (e.g., an unknown block type, an unsupported schema version, or validation failure after an upgrade). 

We need to establish how the public API handles these unservable blocks and how the admin UI communicates their status to administrators without flooding logs, exposing internal exceptions, or serving broken UI components.

## Decision
1. **Public API Behaviour (Phase 2):** 
   - The API will fail closed per block. If a payload cannot be upgraded to its current schema or fails validation, the block is skipped, and valid sibling blocks are returned. 
   - If every eligible block fails, the API returns an empty list in the standard success shape.
   - We will never expose internal exceptions or invalid payloads. 
   - Serving failures will be logged at warning level (with block type, public ID, schema version, and reason) with deduplication/throttling to prevent log flooding.

2. **Serving Health Resolution (Phase 1 Step 2b):**
   - Introduce a shared application-layer service (`ResolveServablePayload`) to centralize serving-health checks (upgrade and validation). This service will be used by both the admin UI and the future public API.
   - Distinguish failure outcomes strictly into structured reason categories: `UnknownType`, `UnsupportedVersion`, `InvalidAfterUpgrade`, and `UpgradeFailed`.

3. **Admin Live Indicator (Phase 1 Step 2b):**
   - The `isLive()` method on `ContentBlock` will remain a pure lifecycle check (status, schedule, enable state) without invoking the payload upgrader.
   - The admin UI table will display three states: **Live**, **Not live**, and **Payload error**. 
   - The specific structured reason category (e.g., "UnsupportedVersion") will be shown only in a tooltip on the "Payload error" badge, preventing UI clutter while hiding internal exceptions.
   - Serving health is only resolved for lifecycle-live rows, cached per request, and paginated. 

4. **View/Edit Behaviour & Publishing/Enabling:**
   - The View page will display the raw payload for administrative debugging and show the structured reason for the failure.
   - The Edit form may perform an in-memory upgrade, but viewing the page will never automatically persist the upgraded data. Persistence requires an explicit Save action.
   - Both Publishing and Enabling actions will reject unservable payloads using the shared `ResolveServablePayload` service.

## Consequences
- The API remains robust, continuing to function even if individual blocks are broken due to developer or schema errors.
- Administrators get clear, actionable feedback when content cannot be served.
- Lifecycle checks remain performant and decoupled from schema upgrades, avoiding expensive processing overhead on non-live rows.
- The shared application service ensures the CMS and API never disagree on whether a payload is servable.
