<?php

declare(strict_types=1);

use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;

it('creates unknown type state', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->unknownType()->create();
    $result = app(ResolveServablePayload::class)($block);
    expect($result->outcome)->toBe(ServingHealthOutcome::UnknownType);
});

it('creates unsupported version state', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->unsupportedVersion()->create();
    $result = app(ResolveServablePayload::class)($block);
    expect($result->outcome)->toBe(ServingHealthOutcome::UnsupportedVersion);
});

it('creates invalid after upgrade state', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->invalidAfterUpgrade()->create();
    $result = app(ResolveServablePayload::class)($block);
    expect($result->outcome)->toBe(ServingHealthOutcome::InvalidAfterUpgrade);
});

it('creates failed upgrade state', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->failedUpgrade()->make();
    $result = app(ResolveServablePayload::class)($block);
    expect($result->outcome)->toBe(ServingHealthOutcome::UpgradeFailed);
});
