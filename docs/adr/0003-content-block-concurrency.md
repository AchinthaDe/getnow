# 0003: Content Block Concurrency

Date: 2026-10-09

## Status
Accepted

## Context
Multiple administrators might attempt to edit the same content block concurrently, or one admin might publish a block while another admin is modifying its payload. We need a concurrency strategy to prevent unpredictable data states.

As documented in `ARCHITECTURE.md` (D7: Concurrency & Locks), the broader system strategy handles concurrency using different approaches based on the domain.

## Decision
1. **Last-Write-Wins:** For Content Blocks, we adopt a strict "last-write-wins" strategy. Whichever transaction commits last will overwrite the previous state.
2. **Locking for State Transitions:** While we do not use optimistic locking (e.g., a version integer on the payload) to reject concurrent form submissions, we do use pessimistic locking (`lockForUpdate()`) strictly for atomic state transitions (Publish, Enable, Disable, Archive) to prevent race conditions during the execution of these actions.

## Consequences
- **Positive:** Simplifies the admin UI since we do not need to build complex merge conflict resolution or version-mismatch error handling in Filament forms.
- **Negative:** If Admin A opens a form, Admin B opens the same form, Admin A saves, and then Admin B saves, Admin A's changes will be silently overwritten by Admin B. Given the small scale of the administration team and the brevity of block content, this is deemed an acceptable trade-off.
