<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Spatie\LaravelData\Data;

final class LayoutSettingsData extends Data
{
    public function __construct(
        public string $site_name,
        public string $default_meta_description,
        public string $search_placeholder,
        public string $footer_brand_text,
    ) {}
}
