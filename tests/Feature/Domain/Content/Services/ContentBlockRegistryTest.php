<?php

declare(strict_types=1);

use App\Domain\Content\Contracts\BlockDefinition;
use App\Domain\Content\Exceptions\DuplicateBlockTypeException;
use App\Domain\Content\Services\ContentBlockRegistry;
use Mockery\MockInterface;

it('throws DuplicateBlockTypeException on duplicate registration', function () {
    $registry = new ContentBlockRegistry;
    /** @var MockInterface&BlockDefinition $mock */
    $mock = Mockery::mock(BlockDefinition::class);
    $mock->shouldReceive('typeKey')->andReturn('duplicate');

    $registry->register($mock);
    $registry->register($mock);
})->throws(DuplicateBlockTypeException::class);

it('returns null for unregistered block type', function () {
    $registry = new ContentBlockRegistry;
    expect($registry->get('unknown'))->toBeNull();
});
