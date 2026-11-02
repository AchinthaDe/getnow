<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsQueryCounts;
use Tests\Support\TestBlocks;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

uses(
    TestCase::class,
)->in('Unit');

uses()->beforeEach(function () {
    TestBlocks::register();
})->in('Feature/Filament', 'Feature/Admin');

/**
 * Assert that the callback executes exactly the expected number of database queries.
 *
 * @template TReturn
 *
 * @param  Closure(): TReturn  $callback
 * @return TReturn
 */
function assertQueryCount(int $expected, Closure $callback): mixed
{
    return AssertsQueryCounts::assertCount($expected, $callback);
}

/**
 * Assert that the callback executes at most the maximum number of database queries.
 *
 * @template TReturn
 *
 * @param  Closure(): TReturn  $callback
 * @return TReturn
 */
function assertQueryCountLessThanOrEqual(int $max, Closure $callback): mixed
{
    return AssertsQueryCounts::assertLessThanOrEqual($max, $callback);
}
