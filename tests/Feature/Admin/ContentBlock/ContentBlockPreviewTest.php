<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\ContentBlock;

use App\Domain\Content\Actions\SaveContentBlockPreviewState;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\EditContentBlock;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('returns 403 forbidden for unauthenticated guest on isolated and page preview routes', function (): void {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    get("/admin/content-blocks/{$block->public_id}/preview/isolated")
        ->assertForbidden();

    get("/admin/content-blocks/{$block->public_id}/preview/page")
        ->assertForbidden();
});

it('returns 403 forbidden for authenticated non-admin user on isolated and page preview routes', function (): void {
    /** @var User $regularUser */
    $regularUser = User::factory()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    actingAs($regularUser);

    get("/admin/content-blocks/{$block->public_id}/preview/isolated")
        ->assertForbidden();

    get("/admin/content-blocks/{$block->public_id}/preview/page")
        ->assertForbidden();
});

it('returns 200 with X-Robots-Tag header for platform admin across draft published disabled and archived blocks', function (string $status, bool $isEnabled): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => PublishStatus::from($status),
        'is_enabled' => $isEnabled,
    ]);

    actingAs($admin);

    $isolated = get("/admin/content-blocks/{$block->public_id}/preview/isolated");
    $isolated->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $page = get("/admin/content-blocks/{$block->public_id}/preview/page");
    $page->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with([
    ['draft', true],
    ['published', true],
    ['published', false],
    ['archived', true],
]);

it('renders at most one data-preview-target and id=preview-target-block in page preview', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR,
        'type' => 'announcement_bar',
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => true,
        'payload' => ['text' => 'Target announcement', 'tone' => 'info'],
    ]);

    actingAs($admin);

    $response = get("/admin/content-blocks/{$block->public_id}/preview/page");
    $response->assertOk();

    $content = $response->getContent();
    preg_match_all('/<[a-z0-9-]+[^>]*data-preview-target="true"/i', $content, $elementMatches);
    expect(count($elementMatches[0]))->toBe(1)
        ->and(substr_count($content, 'id="preview-target-block"'))->toBe(1);
});

it('renders fallback banner when live preview contains invalid draft payload', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR,
        'type' => 'announcement_bar',
        'status' => PublishStatus::PUBLISHED,
        'payload' => ['text' => 'Valid saved text', 'tone' => 'info'],
    ]);

    actingAs($admin);

    // Save an invalid draft into ephemeral cache (empty text fails schema validation)
    app(SaveContentBlockPreviewState::class)(
        $admin->id,
        $block->public_id,
        ['text' => '', 'tone' => 'info'],
        0,
    );

    $response = get("/admin/content-blocks/{$block->public_id}/preview/page?live=1");
    $response->assertOk()
        ->assertSee('id="preview-fallback-banner"', false)
        ->assertSee('Showing last valid saved state — current draft inputs have validation errors.')
        ->assertSee('Valid saved text');
});

it('renders unservable placeholder when stored database payload is corrupt and no draft exists', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR,
        'type' => 'announcement_bar',
        'status' => PublishStatus::PUBLISHED,
        'payload' => ['text' => '', 'corrupt' => true],
    ]);

    actingAs($admin);
    Cache::forget(SaveContentBlockPreviewState::cacheKey($admin->id, $block->public_id));

    $response = get("/admin/content-blocks/{$block->public_id}/preview/isolated");
    $response->assertOk()
        ->assertSee('id="preview-unservable-placeholder"', false)
        ->assertSee('Preview unavailable: stored block payload failed schema validation.');
});

it('escapes html entities in preview avoiding raw xss injection', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR,
        'type' => 'announcement_bar',
        'status' => PublishStatus::PUBLISHED,
        'payload' => ['text' => 'Flash Sale < 50% & "Gifts"', 'tone' => 'info'],
    ]);

    actingAs($admin);

    $response = get("/admin/content-blocks/{$block->public_id}/preview/isolated");
    $response->assertOk()
        ->assertDontSee('< 50%', false)
        ->assertSee('Flash Sale &lt; 50% &amp; &quot;Gifts&quot;', false);
});

it('clears preview cache entry when saving edit form', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'payload' => [
            'text' => 'Original text',
            'tone' => 'info',
        ],
    ]);

    actingAs($admin);

    // Seed preview cache
    app(SaveContentBlockPreviewState::class)(
        $admin->id,
        $block->public_id,
        ['text' => 'Draft in progress', 'tone' => 'info'],
        0,
    );

    $cacheKey = SaveContentBlockPreviewState::cacheKey($admin->id, $block->public_id);
    expect(Cache::has($cacheKey))->toBeTrue();

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->fillForm([
            'payload.text' => 'Final saved text',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Cache::has($cacheKey))->toBeFalse();
});
