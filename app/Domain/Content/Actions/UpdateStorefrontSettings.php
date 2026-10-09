<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Data\StorefrontSettingsData;
use App\Domain\Content\Settings\StorefrontSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateStorefrontSettings
{
    public function __construct(
        private StorefrontSettings $settings,
    ) {}

    public function __invoke(StorefrontSettingsData $data, ?User $actor = null): StorefrontSettingsData
    {
        return DB::transaction(function () use ($data, $actor) {
            $oldProps = [
                'site_name' => $this->settings->site_name,
                'default_meta_description' => $this->settings->default_meta_description,
                'search_placeholder' => $this->settings->search_placeholder,
                'footer_brand_text' => $this->settings->footer_brand_text,
            ];

            $newProps = [
                'site_name' => $data->site_name,
                'default_meta_description' => $data->default_meta_description,
                'search_placeholder' => $data->search_placeholder,
                'footer_brand_text' => $data->footer_brand_text,
            ];

            $changedOld = [];
            $changedNew = [];

            foreach ($newProps as $key => $newValue) {
                if ($oldProps[$key] !== $newValue) {
                    $changedOld[$key] = $oldProps[$key];
                    $changedNew[$key] = $newValue;
                }
            }

            if (empty($changedNew)) {
                return $data;
            }

            $this->settings->site_name = $data->site_name;
            $this->settings->default_meta_description = $data->default_meta_description;
            $this->settings->search_placeholder = $data->search_placeholder;
            $this->settings->footer_brand_text = $data->footer_brand_text;
            $this->settings->save();

            activity('storefront-settings')
                ->causedBy($actor)
                ->withProperties(['old' => $changedOld, 'attributes' => $changedNew])
                ->event('updated')
                ->log('updated storefront settings');

            return $data;
        });
    }
}
