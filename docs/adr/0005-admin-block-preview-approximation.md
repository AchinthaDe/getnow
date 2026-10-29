# 0005: Ephemeral Admin Block Preview Approximation

Date: 2026-10-19

## Status
Accepted (Amends ADR 0001, Decision D7, and ARCHITECTURE.md §1 phase table)

## Context
Decision D7 specified that published content edits go live immediately on Save, deferring revisions and previews to Phase 2. However, admins editing visual blocks (hero carousels, feature banners, service strips) require visual feedback before publishing to prevent storefront disruption.

## Decision
1. Introduce ephemeral cache-backed admin block previews in Phase 1:
   - Isolated single-block preview on `ViewContentBlock`.
   - Native split-screen live preview ('Storefront Preview (Approximation)') on `EditContentBlock` with Desktop, Tablet, and Mobile viewport toggles.
2. Form input changes on `EditContentBlock` synchronize debounced state to cache via `SaveContentBlockPreviewState` without mutating the database row, preserving Decision D7.
3. Persistent revisions, version history, and public API preview tokens remain strictly in Phase 2.
4. Preview routes reside in `app/Http/Controllers/Admin/ContentBlockPreviewController.php` under `admin/content-blocks/{public_id}/preview/` with panel and policy authorization.
5. Preview temporarily depends on the `P1-S1` storefront demo harness (`resources/views/storefront/`, `DemoHomeController`).

## Consequences
- Admins gain real-time visual preview approximation before saving.
- Zero database write overhead or lock contention during preview editing.
- Preview dependencies are cataloged in `DEMO-TEARDOWN.md` for clean removal at teardown.
