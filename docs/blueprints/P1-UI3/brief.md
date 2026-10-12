[P1-UI3 | BRIEF | Claude]

Goal: Add BlockFormRegistry and the AnnouncementBar form schema (app/Filament/Blocks),
  then Create and Edit pages for ContentBlockResource that call the existing Actions.
  Form state for block fields is nested under payload.*.

Depends on (verified from code):
  BlockRegistry::get(string): ?BlockDefinition, ::all(): array<string, BlockDefinition>
  BlockDefinition: typeKey(), schemaVersion(), dataClass(), upgradePayload()
  CreateContentBlock::__invoke(CreateContentBlockData $data, array $rawPayload, ?User $causer = null): ContentBlock
    (always creates status draft, is_enabled true, sort_order 0)
  UpdateContentBlock::__invoke(int $id, UpdateContentBlockData $data, array $rawPayload, ?User $causer = null): ContentBlock
    (throws IllegalStateTransitionException if archived; type and placement are not updatable)
  GetUpgradedBlockPayload::__invoke(string $type, int $schemaVersion, array $payload): BlockPayload
    (throws UnknownBlockType, UnsupportedSchemaVersion, UpgradeFailed, InvalidPayload)
  AnnouncementBarData: text (Min 1, Max 120, NoHtml), link_url (nullable, SafeLink), tone (Tone enum)
  Action ValidationExceptions are keyed payload.<field>, ends_at, type (NOT prefixed with data.)

In scope:
  1. app/Filament/Blocks/BlockFormRegistry.php (+ a BlockForm contract if the plan needs one)
  2. app/Filament/Blocks/AnnouncementBarForm.php
  3. ContentBlockResource/Schemas/ContentBlockForm.php (placement, type, dynamic payload
     group via the registry, starts_at, ends_at)
  4. ContentBlockResource/Pages/CreateContentBlock.php and EditContentBlock.php
  5. Register create and edit in ContentBlockResource::getPages(); add the Create header
     action and Edit row action to the list. No other list changes.
  6. Tests for all of the above, extending existing arch and smoke tests.

Out of scope:
  sort_order editing (no Action writes it, Create hardcodes 0), View page, Publish,
  Archive, Enable, Disable controls (UI4), Delete or bulk actions, menus, API, new block
  types, migrations, packages, changes to any Action.

Read first:
  ARCHITECTURE.md sections 5, 6, 8 and decisions D7, D9. AGENTS.md 'Where code goes',
  'Content rules', 'Coding standards'.
  Files: everything in the step 2 output plus ContentBlockResource.php, ListContentBlocks.php,
  ContentBlockTable.php, StorefrontSettingsForm.php (pattern to mimic),
  ManageStorefrontSettings.php, Placement.php, Tone.php, CreateContentBlockData.php,
  UpdateContentBlockData.php, ContentBlockPolicy.php, ContentBlockRegistry.php,
  ContentBlock.php, AdminTimezoneResolver.php, SafeLink.php, tests/Arch/FilamentWritesTest.php,
  tests/Support/FilamentSmokeTest.php, tests/Support/TestBlocks.php,
  tests/Feature/Domain/Content/Actions/ActionCoverageTest.php.

Decisions made (do not re-decide):
  D-a Registry is an explicit type key > form class map inside BlockFormRegistry. No
      auto-discovery, no ContentServiceProvider change.
  D-b Block form classes return components with names relative to payload (text,
      link_url, tone). The resource form wraps them with statePath('payload').
  D-c Max length single source: add a public const TEXT_MAX_LENGTH = 120 to
      AnnouncementBarData, use it in #[Max(...)] and in the form. This is the only
      permitted app/Domain edit. No schema_version bump (shape unchanged).
  D-d Unknown type: reuse the existing UnknownBlockTypeException.
  D-e Tone labels live in the Filament layer, the domain enum does not implement
      Filament contracts (S3).
  D-f Type and placement are chosen on Create and shown read-only on Edit (not dehydrated).
  D-g After create, redirect to the Edit page.  [defaults apply unless you object]
  D-h Create and Edit pages never offer status or enabled controls.

Acceptance criteria:
  AC-1  BlockFormRegistry returns a schema for every key in BlockRegistry::all(). A test
        fails when a registered type has no form.
  AC-2  Unknown type key throws UnknownBlockTypeException, never null or empty schema.
  AC-3  AnnouncementBar form has exactly text, link_url, tone. text is required with its
        max length read from AnnouncementBarData::TEXT_MAX_LENGTH. tone lists all Tone
        cases. link_url is a plain text input that accepts /path and https:// URLs.
  AC-4  No form field named type (on edit), status, is_enabled, schema_version or sort_order.
  AC-5  No RichEditor, MarkdownEditor, CodeEditor, KeyValue, or Filament ->url() in any
        block form.
  AC-6  Create page: a valid submission calls CreateContentBlock (proof: block is draft,
        created_by is the admin, activity log has event 'created'). Filament's default
        Model::create path is overridden and unreachable.
  AC-7  Edit page: submission calls UpdateContentBlock (proof: activity log 'updated' with
        changed=['payload'] for a text change; no log entry when nothing changed).
        Filament's default $record->update path is overridden.
  AC-8  Edit hydrates through GetUpgradedBlockPayload. A block with a stored payload that
        fails to upgrade or validate (each of the 4 exceptions) opens a controlled state
        (notification and redirect, or a read-only message), never a 500.
  AC-9  Action ValidationExceptions render on the correct fields: payload.text,
        payload.link_url, payload.tone, ends_at. Tests cover text of 121 chars, text with
        HTML, link 'javascript:alert(1)', link 'http://x.test', ends_at before starts_at.
  AC-10 Link values '/shop' and 'https://example.com' are accepted. '' becomes null.
  AC-11 starts_at and ends_at are interpreted in the resolved admin timezone and stored in
        UTC. Test with a non-UTC admin timezone.
  AC-12 Archived blocks cannot be edited from the UI (policy or page guard, plan states
        which). Non-platform-admin users get 403 on create and edit.
  AC-13 Edit page for a published block shows a visible notice that changes go live
        immediately (D7).
  AC-14 No Delete, ForceDelete or Restore action exists on any block page. Routes for
        content-blocks are exactly index, create, edit.
  AC-15 No change under app/Domain except the TEXT_MAX_LENGTH constant, and Policy
        abilities only if create or update are missing or wrong (tests required).
  AC-16 Smoke tests (T2) load list, create and edit pages. Arch tests extended, not
        duplicated. composer check green.

Rules most at risk:
  S4 and C5 (framework base classes write to the model on their own), C4, K3, K4, K2,
  C6, S3, T2, X1.

Known traps:
  1. Filament's CreateRecord and EditRecord save through the model by default.
     Arch tests cannot see vendor code, so AC-6 and AC-7 need behavioral tests.
  2. TextInput->url() rejects relative paths like /shop. Use plain text plus SafeLink.
  3. Filament expects error keys prefixed with the form statePath (data.payload.text).
     The Action throws payload.text. The plan must specify the re-keying and test it.
  4. The Filament page class names collide with the Action class names
     (CreateContentBlock). Plan the aliases.
  5. Edit default header has DeleteAction. Remove it.
  6. This is Filament 5. Verify every API against vendor/filament source, not memory.
     Do not write Filament 3 signatures.
  7. DateTimePicker timezone handling (AC-11) is easy to get wrong silently.
  8. TestBlocks may register extra types in the registry, which can break AC-1 coverage.
  9. Hidden fields for other block types must never leak keys into the payload sent to the Action.

Size guard and checkpoints:
  Expected 12 to 14 files. More than 16 files, or any need to touch an Action, means stop
  and send a CR to split. Plan section 12 must define two checkpoints:
  CP-A registry + AnnouncementBarForm + their tests (composer check green, mini report)
  CP-B pages + resource wiring + page tests (full IMPL-REPORT).

Manual UI script:
  1. As platform admin open /admin, Content blocks list, click New. Expect placement,
     type, text, link, tone, starts at, ends at. No status or enabled controls.
  2. Create with valid text and tone, empty link. Expect redirect to Edit, Draft badge in list.
  3. Text of 121 chars: error on text, nothing created. Text '<b>x</b>': error on text.
  4. Link 'javascript:alert(1)' and 'http://example.com': error. '/shop' and
     'https://example.com': accepted.
  5. ends at earlier than starts at: error on ends at.
  6. Edit: type and placement visible and not editable. Change text, save, list updates.
     Save again with no change: no new activity entry.
  7. Set a schedule 09:00 admin time: list shows 09:00, DB stores UTC.
  8. Archived block (make via factory or tinker): cannot be edited.
  9. No Delete, Publish or Enable controls on create or edit.
  10. Non-admin user: 403.

Step-specific checks (Layer A extras):
  php artisan route:list --path=admin/content-blocks
  git diff main...HEAD --stat -- database composer.json composer.lock
  git diff main...HEAD --stat -- app/Domain
  Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch 'DeleteAction','ForceDeleteAction','RestoreAction','->url()','RichEditor','MarkdownEditor','CodeEditor','KeyValue'