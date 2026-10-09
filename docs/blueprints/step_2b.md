# Phase 1 Step 2b - UI Step 1 (Prerequisites)

## Build Order

1. **Address all prerequisites**:
   - `User::isAdmin()` and `canAccessPanel` updates.
   - `GetBlockServingStatus` implementation.
   - Core regression tests for schema upgrades.
2. **Build Policy + Timezone Config + Filament List table + table badges**. (Checkpoint)
3. **Implement BlockFormRegistry + Create/Edit forms + mount redirects + save handling**. (Checkpoint)
4. **Implement View page + header/row actions**. (Checkpoint)

## Paths and Namespaces

All Filament code resides strictly under `App\Filament\Admin\...`:
- `app/Filament/Admin/Resources/ContentBlockResource.php`
- `app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php`
- `app/Filament/Admin/Resources/ContentBlockResource/Pages/CreateContentBlock.php`
- `app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php`
- `app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php`
- `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php`
- `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php`
- `app/Filament/Admin/Blocks/BlockFormRegistry.php`

## Filament UI & Hydration Specifics

- **Form Hydration**: Form state hydration must convert the Servable `BlockPayload` into an array specifically matching the form schema structure.
- **GetBlockServingStatus Memoization**: Memo cache keyed dynamically on `id + updated_at + status + is_enabled`. Invalidation/resolver execution must only trigger if `isLive()` returns true (with `log: false` passed to the resolver).
- **UI Text Updates**:
  - View page redirect notification in `mount()` triggers a warning.
  - Display a distinct warning notice if an admin edits a currently LIVE block.
  - Use `cancel()` instead of "halts" for the Publish/Enable action test line.
  - **Explicit requirement**: Broken blocks that are NOT lifecycle-live must show "Not live" only.

## Restored Testing Requirements

- Ensure every `BlockDefinition` correctly provides a form schema.
- Assure form-rule parity.
- Verify that a block type change during 'Create' cleanly resets the payload.
- Verify `activity` causer for Create/Edit propagates through Livewire to the actions.
- Test that saving a record that was archived elsewhere surfaces a notification safely without throwing 500.
- Use a spy to ensure the Infolist view *never* invokes the upgrader.
- Assert that an invalid `ADMIN_TIMEZONE` silently falls back to UTC and logs a warning.
- The `/edit` direct-URL redirect MUST be tested separately for `archived`, `unknown-type`, and `failed-upgrade` blocks, asserting the proper notification triggers for each case.
