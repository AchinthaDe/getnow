<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Content;

use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\Tone;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Domain\Content\Settings\StorefrontSettings;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\TestBlocks;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withHeaders;

beforeEach(function (): void {
    TestBlocks::register();
    RateLimiter::clear('content-api');
    RateLimiter::clear('content-api:127.0.0.1');
    RateLimiter::clear('content-api:'.sha1('127.0.0.1'));
});

it('returns HTTP 200 with layout data and meta without authentication', function (): void {
    ContentBlock::factory()->published()->enabled()->create([
        'type' => 'announcement_bar',
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'payload' => [
            'text' => 'Welcome to GetNow',
            'link_url' => null,
            'tone' => Tone::INFO->value,
        ],
    ]);

    $response = getJson('/api/v1/content/layout');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'settings',
            'placements',
            'menus',
        ],
        'meta' => [
            'api_version',
            'revision',
            'generated_at',
        ],
    ]);

    $announcementBar = $response->json('data.placements.announcement_bar.0');
    expect($announcementBar)->not->toBeNull();
    expect($announcementBar['data']['tone'])->toBe('info');
    expect($announcementBar['data']['link_url'])->toBeNull();

    $revision = (string) $response->json('meta.revision');
    expect($revision)->not->toBeEmpty();
    $response->assertHeader('ETag', '"'.$revision.'"');
});

it('handles conditional GET returning 304 on matching ETag and 200 on stale ETag', function (): void {
    ContentBlock::factory()->published()->enabled()->create([
        'type' => 'announcement_bar',
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'payload' => [
            'text' => 'Welcome to GetNow',
            'link_url' => null,
            'tone' => Tone::INFO->value,
        ],
    ]);

    $initialResponse = getJson('/api/v1/content/layout');
    $initialResponse->assertOk();
    $rev = (string) $initialResponse->json('meta.revision');
    expect($rev)->not->toBeEmpty();

    // 1. Strong matching ETag
    $strongResp = withHeaders(['If-None-Match' => '"'.$rev.'"'])->get('/api/v1/content/layout');
    $strongResp->assertStatus(304);
    $strongResp->assertHeader('ETag', '"'.$rev.'"');
    expect($strongResp->getContent())->toBe('');

    // 2. Weak matching ETag
    $weakResp = withHeaders(['If-None-Match' => 'W/"'.$rev.'"'])->get('/api/v1/content/layout');
    $weakResp->assertStatus(304);
    $weakResp->assertHeader('ETag', '"'.$rev.'"');
    expect($weakResp->getContent())->toBe('');

    // 3. Wildcard ETag
    $wildcardResp = withHeaders(['If-None-Match' => '*'])->get('/api/v1/content/layout');
    $wildcardResp->assertStatus(304);
    expect($wildcardResp->getContent())->toBe('');

    // 4. Comma list containing matching strong token
    $listResp = withHeaders(['If-None-Match' => '"other-hash", "'.$rev.'"'])->get('/api/v1/content/layout');
    $listResp->assertStatus(304);
    expect($listResp->getContent())->toBe('');

    // 5. Comma list containing matching weak token
    $weakListResp = withHeaders(['If-None-Match' => '"other-hash", W/"'.$rev.'"'])->get('/api/v1/content/layout');
    $weakListResp->assertStatus(304);
    expect($weakListResp->getContent())->toBe('');

    // 6. Stale revision token returns HTTP 200 with JSON payload
    $staleResp = withHeaders(['If-None-Match' => '"stale-revision-hash"'])->getJson('/api/v1/content/layout');
    $staleResp->assertOk();
    $staleResp->assertJsonStructure(['data', 'meta']);
    expect($staleResp->json('meta.revision'))->toBe($rev);
});

it('throttles requests exceeding configured rate limit with HTTP 429', function (): void {
    RateLimiter::clear('content-api');
    RateLimiter::clear('content-api:127.0.0.1');
    RateLimiter::clear('content-api:'.sha1('127.0.0.1'));
    config(['content.rate_limit_per_minute' => 2]);

    get('/api/v1/content/layout')->assertOk();
    get('/api/v1/content/layout')->assertOk();
    get('/api/v1/content/layout')->assertStatus(429);
});

it('matches the canonical contract fixture schema', function (): void {
    $settings = app(StorefrontSettings::class);
    $settings->site_name = 'GetNow Marketplace';
    $settings->default_meta_description = 'Wholesale mobile phones and consumer electronics.';
    $settings->search_placeholder = 'Search models, brands, or SKUs...';
    $settings->footer_brand_text = 'GetNow Electronics Wholesale Ltd.';
    $settings->save();

    ContentBlock::factory()->published()->enabled()->create([
        'type' => 'announcement_bar',
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'payload' => [
            'text' => 'Welcome to GetNow Wholesale Electronics',
            'link_url' => null,
            'tone' => Tone::INFO->value,
        ],
    ]);

    $mainNav = Menu::factory()->create([
        'key' => MenuKey::MAIN_NAV->value,
        'title' => 'Main Navigation',
    ]);
    $rootItem = MenuItem::factory()->create([
        'menu_id' => $mainNav->id,
        'parent_id' => null,
        'label' => 'Phones',
        'url' => '/phones',
        'icon' => null,
        'opens_in_new_tab' => false,
        'is_enabled' => true,
        'sort_order' => 0,
    ]);
    MenuItem::factory()->create([
        'menu_id' => $mainNav->id,
        'parent_id' => $rootItem->id,
        'label' => 'Apple',
        'url' => '/phones/apple',
        'icon' => null,
        'opens_in_new_tab' => false,
        'is_enabled' => true,
        'sort_order' => 0,
    ]);

    $fixtureJson = (string) file_get_contents(base_path('tests/Fixtures/contracts/content-layout-v1.json'));
    /** @var array<string, mixed> $expectedFixture */
    $expectedFixture = json_decode($fixtureJson, true, 512, JSON_THROW_ON_ERROR);

    $response = getJson('/api/v1/content/layout');
    $response->assertOk();
    /** @var array<string, mixed> $actualResponse */
    $actualResponse = $response->json();

    $assertSchemaMatches = function (mixed $expected, mixed $actual, string $path = '') use (&$assertSchemaMatches): void {
        if (is_array($expected)) {
            expect($actual)->toBeArray();
            if (array_is_list($expected)) {
                expect(array_is_list($actual))->toBeTrue("Expected list at path '$path'");
                expect(count($actual))->toBe(count($expected), "Array count mismatch at path '$path'");
                foreach ($expected as $i => $item) {
                    $assertSchemaMatches($item, $actual[$i], "$path.[$i]");
                }
            } else {
                $expectedKeys = array_keys($expected);
                $actualKeys = array_keys($actual);
                sort($expectedKeys);
                sort($actualKeys);
                expect($actualKeys)->toBe($expectedKeys, "Key set mismatch at path '$path'");

                foreach ($expected as $key => $expectedVal) {
                    $assertSchemaMatches($expectedVal, $actual[$key], "$path.$key");
                }
            }
        } elseif ($expected === '<ulid>') {
            expect($actual)->toBeString();
            expect((int) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', (string) $actual))->toBe(1, "Expected ULID at path '$path', got '$actual'");
        } elseif ($expected === '<hash>') {
            expect($actual)->toBeString();
            expect((int) preg_match('/^[0-9a-f]{64}$/i', (string) $actual))->toBe(1, "Expected 64-char hex hash at path '$path', got '$actual'");
        } elseif ($expected === '<iso8601>') {
            expect($actual)->toBeString();
            expect((int) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) $actual))->toBe(1, "Expected ISO8601 Zulu string at path '$path', got '$actual'");
        } else {
            expect(gettype($actual))->toBe(gettype($expected), "Type mismatch at path '$path'");
            expect($actual)->toBe($expected, "Value mismatch at path '$path'");
        }
    };

    $assertSchemaMatches($expectedFixture, $actualResponse, 'root');
});
