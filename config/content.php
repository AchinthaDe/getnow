<?php

declare(strict_types=1);

return [
    'rate_limit_per_minute' => (int) env('CONTENT_API_RATE_LIMIT_PER_MINUTE', 60),
];
