<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

final class AssertsQueryCounts
{
    private function __construct() {}

    /**
     * Assert that the callback executes exactly the expected number of database queries.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function assertCount(int $expectedCount, Closure $callback): mixed
    {
        return self::runWithQueryLogging(
            $callback,
            static function (int $actualCount, array $queries) use ($expectedCount): void {
                if ($actualCount !== $expectedCount) {
                    $formatted = self::formatQueries($queries);
                    Assert::fail("Expected exactly {$expectedCount} database queries, but {$actualCount} were executed.\n\nExecuted queries:\n{$formatted}");
                }
            }
        );
    }

    /**
     * Assert that the callback executes at most the maximum number of database queries.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function assertLessThanOrEqual(int $maxCount, Closure $callback): mixed
    {
        return self::runWithQueryLogging(
            $callback,
            static function (int $actualCount, array $queries) use ($maxCount): void {
                if ($actualCount > $maxCount) {
                    $formatted = self::formatQueries($queries);
                    Assert::fail("Expected at most {$maxCount} database queries, but {$actualCount} were executed.\n\nExecuted queries:\n{$formatted}");
                }
            }
        );
    }

    /**
     * Execute callback while capturing query log and enforce cleanup in finally block.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @param  Closure(int, array<int, array{query: string, bindings: array<int|string, mixed>, time: float}>): void  $assertion
     * @return TReturn
     */
    private static function runWithQueryLogging(Closure $callback, Closure $assertion): mixed
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $result = $callback();
            /** @var array<int, array{query: string, bindings: array<int|string, mixed>, time: float}> $queries */
            $queries = DB::getQueryLog();
            $assertion(count($queries), $queries);

            return $result;
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    /**
     * Format captured queries into readable string for assertion failure messages.
     *
     * @param  array<int, array{query: string, bindings: array<int|string, mixed>, time: float}>  $queries
     */
    private static function formatQueries(array $queries): string
    {
        if ($queries === []) {
            return '  (no queries executed)';
        }

        $lines = [];
        foreach ($queries as $index => $queryData) {
            $queryNumber = $index + 1;
            $sql = $queryData['query'];
            $bindingsJson = json_encode($queryData['bindings'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
            $time = number_format($queryData['time'], 2);
            $lines[] = "  [{$queryNumber}] ({$time}ms) {$sql} [bindings: {$bindingsJson}]";
        }

        return implode("\n", $lines);
    }
}
