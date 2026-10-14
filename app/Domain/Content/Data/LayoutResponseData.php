<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Spatie\LaravelData\Data;

final class LayoutResponseData extends Data
{
    public function __construct(
        public LayoutData $data,
        public LayoutMetaData $meta,
    ) {}
}
