<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

class CreateStorefrontSettings extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('storefront.site_name', 'Storefront');
        $this->migrator->add('storefront.default_meta_description', 'Welcome to the storefront');
        $this->migrator->add('storefront.search_placeholder', 'Search...');
        $this->migrator->add('storefront.footer_brand_text', 'Storefront');
    }

    public function down(): void
    {
        $this->migrator->delete('storefront.site_name');
        $this->migrator->delete('storefront.default_meta_description');
        $this->migrator->delete('storefront.search_placeholder');
        $this->migrator->delete('storefront.footer_brand_text');
    }
}
