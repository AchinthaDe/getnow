<?php

declare(strict_types=1);

use App\Domain\Content\Blocks\AnnouncementBar\AnnouncementBarV1ToV2Upgrader;

it('upgrades stored v1 announcement bar payloads to v2 with null defaults', function () {
    $payload = [
        'text' => 'Spring Offers',
        'link_url' => '/shop',
        'tone' => 'info',
    ];

    $migrated = (new AnnouncementBarV1ToV2Upgrader)($payload);

    expect($migrated['text'])->toBe('Spring Offers')
        ->and($migrated['link_url'])->toBe('/shop')
        ->and($migrated['tone'])->toBe('info')
        ->and($migrated['secondary_text'])->toBeNull()
        ->and($migrated['locale_text'])->toBeNull();
});

it('preserves existing secondary and locale text if already present during upgrade', function () {
    $payload = [
        'text' => 'Spring Offers',
        'link_url' => '/shop',
        'tone' => 'info',
        'secondary_text' => 'Free shipping',
        'locale_text' => 'LK / LKR',
    ];

    $migrated = (new AnnouncementBarV1ToV2Upgrader)($payload);

    expect($migrated['secondary_text'])->toBe('Free shipping')
        ->and($migrated['locale_text'])->toBe('LK / LKR');
});
