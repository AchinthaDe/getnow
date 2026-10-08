<?php

declare(strict_types=1);

use App\Domain\Content\Models\ContentBlock;
use Carbon\Carbon;

it('orders correctly across DB layers using starts_at DESC NULLS LAST', function () {
    /** @var ContentBlock $b1 */
    $b1 = ContentBlock::factory()->create(['sort_order' => 0, 'starts_at' => Carbon::parse('2026-01-01')]);
    /** @var ContentBlock $b2 */
    $b2 = ContentBlock::factory()->create(['sort_order' => 0, 'starts_at' => null]);
    /** @var ContentBlock $b3 */
    $b3 = ContentBlock::factory()->create(['sort_order' => 0, 'starts_at' => Carbon::parse('2026-12-31')]);
    /** @var ContentBlock $b4 */
    $b4 = ContentBlock::factory()->create(['sort_order' => 1, 'starts_at' => Carbon::parse('2026-12-31')]);

    $results = ContentBlock::servingOrder()->get();

    // Expected order:
    // 1. $b3 (sort_order 0, starts_at latest)
    // 2. $b1 (sort_order 0, starts_at earlier)
    // 3. $b2 (sort_order 0, starts_at null - NULLS LAST)
    // 4. $b4 (sort_order 1)

    expect($results->pluck('id')->toArray())->toBe([
        $b3->id,
        $b1->id,
        $b2->id,
        $b4->id,
    ]);
});
