<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\ContentBlock;

use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ViewContentBlock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\Support\FilamentSmokeTest;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

class ContentBlockViewSmokeTest extends TestCase
{
    use FilamentSmokeTest;

    public function test_renders_view_page_for_platform_admin_via_smoke_test(): void
    {
        /** @var ContentBlock $block */
        $block = ContentBlock::factory()->create();

        $this->assertViewPageRenders(ViewContentBlock::class, $block);
    }
}

it('resolves view route with public_id and returns 404 for numeric id', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    actingAs($admin);

    expect(ContentBlockResource::getUrl('view', ['record' => $block]))
        ->toContain((string) $block->public_id);

    get('/admin/content-blocks/'.$block->public_id)
        ->assertSuccessful();

    get('/admin/content-blocks/'.$block->id)
        ->assertNotFound();

    get('/admin/content-blocks/non-existent-ulid')
        ->assertNotFound();
});

it('renders all metadata fields on view page across draft published and archived blocks', function (string $status) {
    Config::set('admin.timezone', 'Asia/Colombo');

    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var User $creator */
    $creator = User::factory()->create(['name' => 'Creator User', 'email' => 'creator@example.com']);
    /** @var User $updater */
    $updater = User::factory()->create(['name' => 'Updater User', 'email' => 'updater@example.com']);

    $startsAtUtc = Carbon::parse('2026-10-15 03:30:00', 'UTC');
    $endsAtUtc = Carbon::parse('2026-10-15 05:30:00', 'UTC');

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => PublishStatus::from($status),
        'starts_at' => $startsAtUtc,
        'ends_at' => $endsAtUtc,
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);

    actingAs($admin);

    Livewire::test(ViewContentBlock::class, ['record' => $block->public_id])
        ->assertSeeText($block->public_id)
        ->assertSeeText('Announcement Bar')
        ->assertSeeText(ucfirst($status))
        ->assertSeeText('2026-10-15 09:00:00')
        ->assertSeeText('2026-10-15 11:00:00')
        ->assertSeeText('Creator User')
        ->assertSeeText('Updater User')
        ->assertSeeText((string) $block->schema_version);
})->with(['draft', 'published', 'archived']);

it('renders announcement bar payload read only with plain text link', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'payload' => [
            'text' => 'Winter sale banner',
            'link_url' => 'https://example.com/winter-sale',
            'tone' => 'info',
        ],
    ]);

    actingAs($admin);

    $component = Livewire::test(ViewContentBlock::class, ['record' => $block->public_id]);

    expect($component->get('data.payload.text'))->toBe('Winter sale banner')
        ->and($component->get('data.payload.link_url'))->toBe('https://example.com/winter-sale')
        ->and($component->get('data.payload.tone'))->toBe('info');

    $component->assertDontSeeHtml('<a href="https://example.com/winter-sale"');

    /** @var ViewContentBlock $instance */
    $instance = $component->instance();
    expect($instance->form->isDisabled())->toBeTrue();
});

it('redirects to index with danger notification when stored payload fails hydration', function (string $factoryState) {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->{$factoryState}()->create();

    actingAs($admin);

    Livewire::test(ViewContentBlock::class, ['record' => $block->public_id])
        ->assertRedirect(ContentBlockResource::getUrl('index'))
        ->assertNotified();
})->with([
    'unknownType',
    'unsupportedVersion',
    'invalidAfterUpgrade',
    'failedUpgrade',
]);

it('forbids non admin from viewing block', function () {
    /** @var User $user */
    $user = User::factory()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    actingAs($user);

    get('/admin/content-blocks/'.$block->public_id)
        ->assertForbidden();
});

it('allows admin to view archived blocks', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->archived()->create();

    actingAs($admin);

    get('/admin/content-blocks/'.$block->public_id)
        ->assertSuccessful();

    Livewire::test(ViewContentBlock::class, ['record' => $block->public_id])
        ->assertSuccessful();
});

it('renders promo_slide block payload on view page', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'promo_slide',
        'placement' => Placement::HOME_HERO_CAROUSEL->value,
        'schema_version' => 1,
        'payload' => [
            'theme' => 'sky',
            'label' => 'Deals Slide',
            'eyebrow' => 'FLASH SALE',
            'title_line_1' => 'Smartphones on Sale',
            'title_line_2' => 'Up to 30% Off',
            'description' => 'Grab genuine flagships at wholesale rates.',
            'cta_label' => 'Shop Deals',
            'cta_url' => '/shop?deals=1',
            'image_url' => '/images/promo/phone.png',
            'image_alt' => 'Smartphone banner',
        ],
    ]);

    actingAs($admin);

    $component = Livewire::test(ViewContentBlock::class, ['record' => $block->public_id]);
    $component->assertSuccessful();

    expect($component->get('data.payload.eyebrow'))->toBe('FLASH SALE')
        ->and($component->get('data.payload.title_line_1'))->toBe('Smartphones on Sale')
        ->and($component->get('data.payload.label'))->toBe('Deals Slide')
        ->and($component->get('data.payload.cta_url'))->toBe('/shop?deals=1');
});

it('renders brand_strip block payload with nested brands repeater array on view page', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'brand_strip',
        'placement' => Placement::HOME_BRAND_STRIP->value,
        'schema_version' => 1,
        'payload' => [
            'eyebrow' => 'GOOD COMPANY',
            'title' => 'Great brands.',
            'brands' => [
                ['label' => 'Apple', 'url' => '/shop?brand=Apple'],
                ['label' => 'Samsung', 'url' => '/shop?brand=Samsung'],
                ['label' => 'Sony', 'url' => '/shop?brand=Sony'],
            ],
        ],
    ]);

    actingAs($admin);

    $component = Livewire::test(ViewContentBlock::class, ['record' => $block->public_id]);
    $component->assertSuccessful();

    $brands = $component->get('data.payload.brands');
    expect($component->get('data.payload.eyebrow'))->toBe('GOOD COMPANY')
        ->and($component->get('data.payload.title'))->toBe('Great brands.')
        ->and(array_values(is_array($brands) ? $brands : []))->toBe([
            ['label' => 'Apple', 'url' => '/shop?brand=Apple'],
            ['label' => 'Samsung', 'url' => '/shop?brand=Samsung'],
            ['label' => 'Sony', 'url' => '/shop?brand=Sony'],
        ]);
});
