<?php

declare(strict_types=1);

use App\Domain\Content\Models\ContentBlock;
use Carbon\Carbon;

it('orders correctly across DB layers using starts_at DESC NULLS LAST', function () {
    /** @var ContentBlock $b1 */
    $b1 = ContentBlock::factory()->create(['sort_order' => 0, 'starts_at' => Carbon::parse('2026-01-01')]);
    /** @var ContentBlock $b2 */
    $b2 = ContentBlock::factory()->create(['sort_order' => 0, 'starts_at' => null]);
    /** @var ContentBlock $b2_tie */
    $b2_tie = ContentBlock::factory()->create(['sort_order' => 0, 'starts_at' => null]);
    /** @var ContentBlock $b3 */
    $b3 = ContentBlock::factory()->create(['sort_order' => 0, 'starts_at' => Carbon::parse('2026-12-31')]);
    /** @var ContentBlock $b3_tie */
    $b3_tie = ContentBlock::factory()->create(['sort_order' => 0, 'starts_at' => Carbon::parse('2026-12-31')]);
    /** @var ContentBlock $b4 */
    $b4 = ContentBlock::factory()->create(['sort_order' => 1, 'starts_at' => Carbon::parse('2026-12-31')]);

    $results = ContentBlock::servingOrder()->get();

    // Expected order:
    // 1. $b3_tie (sort_order 0, starts_at latest, higher id)
    // 2. $b3 (sort_order 0, starts_at latest, lower id)
    // 3. $b1 (sort_order 0, starts_at earlier)
    // 4. $b2_tie (sort_order 0, starts_at null - NULLS LAST, higher id)
    // 5. $b2 (sort_order 0, starts_at null - NULLS LAST, lower id)
    // 6. $b4 (sort_order 1)

    expect($results->pluck('id')->toArray())->toBe([
        $b3_tie->id,
        $b3->id,
        $b1->id,
        $b2_tie->id,
        $b2->id,
        $b4->id,
    ]);
});
