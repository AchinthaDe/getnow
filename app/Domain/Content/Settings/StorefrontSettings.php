<?php

declare(strict_types=1);

namespace App\Domain\Content\Settings;

use Spatie\LaravelSettings\Settings;

class StorefrontSettings extends Settings
{
    public string $site_name;

    public string $default_meta_description;

    public string $search_placeholder;

    public string $footer_brand_text;

    public static function group(): string
    {
        return 'storefront';
    }
}
