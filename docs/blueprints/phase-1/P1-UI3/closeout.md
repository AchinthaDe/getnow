[P1-UI3 | CLOSEOUT]
Shipped: BlockFormRegistry, AnnouncementBarForm, Create and Edit pages calling the Actions, policy denies editing archived blocks, public_id edit URLs.
Deviations accepted: CR-01 (getUrl() override in ContentBlockResource, proved necessary by MUT-4); CR-02 (smoke helper converted to a static class).
Follow-ups: no Action writes sort_order (needed before P1-API1); AnnouncementBarBlock::upgradePayload has a test-only failure hook that TestFailedUpgradeBlock makes redundant; 20 starter-kit PHP files lack strict_types.
Next step depends on: Edit page and policy as shipped; UI4 adds the View page and the Publish, Archive, Enable and Disable actions.
ADRs added: none.
