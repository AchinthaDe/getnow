<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Content\Actions\UpdateStorefrontSettings;
use App\Domain\Content\Data\StorefrontSettingsData;
use App\Domain\Content\Settings\StorefrontSettings;
use App\Filament\Admin\Schemas\StorefrontSettingsForm;
use Filament\Facades\Filament;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;

final class ManageStorefrontSettings extends SettingsPage
{
    public static function getNavigationGroup(): string
    {
        return 'Content';
    }

    protected static string $settings = StorefrontSettings::class;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->is_platform_admin === true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(StorefrontSettingsForm::schema());
    }

    public function save(): void
    {
        // Skip default lifecycle hooks to enforce strict DTO validation.
        $data = StorefrontSettingsData::prepareValidated($this->form->getState());

        app(UpdateStorefrontSettings::class)($data, Filament::auth()->user());

        $this->getSavedNotification()?->send();
    }
}
