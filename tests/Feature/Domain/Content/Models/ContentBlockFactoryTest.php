<?php

declare(strict_types=1);

use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ContentBlockRegistry;
use App\Domain\Content\Services\ResolveServablePayload;
use Tests\Support\TestBlocks;

beforeEach(function () {
    TestBlocks::register();
});

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
    $block = ContentBlock::factory()->failedUpgrade()->create();
    $result = app(ResolveServablePayload::class)($block);
    expect($result->outcome)->toBe(ServingHealthOutcome::UpgradeFailed);
});

it('throws LogicException when failed upgrade test block is not registered', function () {
    // Unregister it by rebinding a fresh registry
    app()->singleton(BlockRegistry::class, ContentBlockRegistry::class);

    expect(fn () => ContentBlock::factory()->failedUpgrade()->create())
        ->toThrow(LogicException::class, 'The test_failed_upgrade_block definition is not registered.');
});
