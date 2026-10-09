<?php

declare(strict_types=1);

use App\Domain\Content\Enums\Placement;

it('returns allowed block types', function () {
    expect(Placement::ANNOUNCEMENT_BAR->allowedBlockTypes())->toBe(['announcement_bar']);
});

it('returns max count', function () {
    expect(Placement::ANNOUNCEMENT_BAR->maxCount())->toBe(1);
});
