<?php

declare(strict_types=1);

use App\Domain\Content\Data\UpdateContentBlockData;

it('omits immutable fields from update DTO', function () {
    $reflection = new ReflectionClass(UpdateContentBlockData::class);
    $properties = collect($reflection->getProperties())->map->getName()->toArray();

    expect($properties)->toContain('starts_at', 'ends_at')
        ->and($properties)->not->toContain('type', 'placement', 'status', 'is_enabled', 'sort_order');
});
