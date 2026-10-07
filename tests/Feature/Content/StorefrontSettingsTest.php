<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domain\Content\Actions\UpdateStorefrontSettings;
use App\Domain\Content\Data\StorefrontSettingsData;
use App\Domain\Content\Settings\StorefrontSettings;
use App\Filament\Admin\Pages\ManageStorefrontSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\ActivityLogger;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FilamentSmokeTest;
use Tests\TestCase;

class StorefrontSettingsTest extends TestCase
{
    use FilamentSmokeTest, RefreshDatabase;

    public function test_updates_settings_and_writes_activity_log_on_exact_changes(): void
    {
        $settings = app(StorefrontSettings::class);
        $settings->site_name = 'Old Name';
        $settings->save();
        $actor = User::factory()->platformAdmin()->create();

        $data = StorefrontSettingsData::prepareValidated([
            'site_name' => 'New Name',
            'default_meta_description' => $settings->default_meta_description,
            'search_placeholder' => $settings->search_placeholder,
            'footer_brand_text' => $settings->footer_brand_text,
        ]);

        app(UpdateStorefrontSettings::class)($data, $actor);

        $settings->refresh();
        $this->assertEquals('New Name', $settings->site_name);

        $activity = Activity::where('event', 'updated')->latest()->first();
        $this->assertNotNull($activity);
        $this->assertEquals($actor->id, $activity->causer_id);
        $this->assertCount(1, $activity->properties['old']);
        $this->assertEquals('Old Name', $activity->properties['old']['site_name']);
        $this->assertEquals('New Name', $activity->properties['attributes']['site_name']);
    }

    public function test_does_not_write_activity_log_if_no_changes_occur(): void
    {
        $settings = app(StorefrontSettings::class);
        $countBefore = Activity::count();

        $data = StorefrontSettingsData::prepareValidated([
            'site_name' => $settings->site_name,
            'default_meta_description' => $settings->default_meta_description,
            'search_placeholder' => $settings->search_placeholder,
            'footer_brand_text' => $settings->footer_brand_text,
        ]);

        app(UpdateStorefrontSettings::class)($data);

        $this->assertEquals($countBefore, Activity::count());
    }

    public function test_rolls_back_transaction_and_settings_on_failure(): void
    {
        $settings = app(StorefrontSettings::class);
        $settings->site_name = 'Original Name';
        $settings->save();

        $loggerMock = \Mockery::mock(ActivityLogger::class)->shouldIgnoreMissing();
        $loggerMock->shouldReceive('causedBy')->andThrow(new \RuntimeException('DB Error'));
        app()->instance(ActivityLogger::class, $loggerMock);

        $data = StorefrontSettingsData::prepareValidated([
            'site_name' => 'New Name',
            'default_meta_description' => $settings->default_meta_description,
            'search_placeholder' => $settings->search_placeholder,
            'footer_brand_text' => $settings->footer_brand_text,
        ]);

        try {
            app(UpdateStorefrontSettings::class)($data);
        } catch (\RuntimeException $e) {
            // Expected
        }

        $settings->refresh();
        $this->assertEquals('Original Name', $settings->site_name);
    }

    public function test_trims_input_correctly_and_fails_on_angle_brackets(): void
    {
        $validData = [
            'site_name' => '  Trimmed Name  ',
            'default_meta_description' => 'desc',
            'search_placeholder' => 'search',
            'footer_brand_text' => 'footer',
        ];

        $dto = StorefrontSettingsData::prepareValidated($validData);
        $this->assertEquals('Trimmed Name', $dto->site_name);

        $this->expectException(ValidationException::class);
        StorefrontSettingsData::prepareValidated(array_merge($validData, ['site_name' => '<div>']));
    }

    public function test_enforces_exact_max_lengths(): void
    {
        $validData = [
            'site_name' => str_repeat('A', 80),
            'default_meta_description' => 'desc',
            'search_placeholder' => 'search',
            'footer_brand_text' => 'footer',
        ];

        $dto = StorefrontSettingsData::prepareValidated($validData);
        $this->assertEquals(str_repeat('A', 80), $dto->site_name);

        $this->expectException(ValidationException::class);
        StorefrontSettingsData::prepareValidated(array_merge($validData, ['site_name' => str_repeat('A', 81)]));
    }

    public function test_logs_null_causer_for_seeder_path(): void
    {
        $settings = app(StorefrontSettings::class);

        $data = StorefrontSettingsData::prepareValidated([
            'site_name' => 'Seeder Name',
            'default_meta_description' => 'desc',
            'search_placeholder' => 'search',
            'footer_brand_text' => 'footer',
        ]);

        auth()->logout();

        app(UpdateStorefrontSettings::class)($data, null);

        $activity = Activity::where('event', 'updated')->latest()->first();
        $this->assertNull($activity->causer_id);
    }

    public function test_allows_platform_admins_to_view_settings_page(): void
    {
        $this->assertPageRenders(ManageStorefrontSettings::class);
    }

    public function test_denies_non_admins_from_viewing_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(ManageStorefrontSettings::getUrl())->assertForbidden();
        Livewire::actingAs($user)->test(ManageStorefrontSettings::class)->assertForbidden();
    }

    public function test_saves_settings_through_livewire(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        Livewire::actingAs($admin)->test(ManageStorefrontSettings::class)
            ->fillForm([
                'site_name' => '  Livewire Store  ',
                'default_meta_description' => 'desc',
                'search_placeholder' => 'search',
                'footer_brand_text' => 'footer',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $settings = app(StorefrontSettings::class);
        $this->assertEquals('Livewire Store', $settings->site_name);
    }

    public function test_validates_rules_exactly_via_livewire(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        // HTML tag rejection
        Livewire::actingAs($admin)->test(ManageStorefrontSettings::class)
            ->fillForm(['site_name' => '<script>'])
            ->call('save')
            ->assertHasFormErrors(['site_name']);

        // Empty/Whitespace rejection
        Livewire::actingAs($admin)->test(ManageStorefrontSettings::class)
            ->fillForm(['site_name' => '   '])
            ->call('save')
            ->assertHasFormErrors(['site_name']);
    }

    public function test_has_trim_first_parity_between_dto_and_livewire(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $eightyChars = str_repeat('A', 80);
        $input = $eightyChars.'   '; // 83 chars, but 80 when trimmed

        // DTO passes
        $dto = StorefrontSettingsData::prepareValidated([
            'site_name' => $input,
            'default_meta_description' => 'desc',
            'search_placeholder' => 'search',
            'footer_brand_text' => 'footer',
        ]);
        $this->assertEquals($eightyChars, $dto->site_name);

        // Livewire passes (Livewire trims empty spaces on the server side before validation)
        Livewire::actingAs($admin)->test(ManageStorefrontSettings::class)
            ->fillForm([
                'site_name' => $input,
                'default_meta_description' => 'desc',
                'search_placeholder' => 'search',
                'footer_brand_text' => 'footer',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $settings = app(StorefrontSettings::class);
        $settings->refresh();
        $this->assertEquals($eightyChars, $settings->site_name);
    }

    public function test_rejects_non_string_input_safely(): void
    {
        $this->expectException(ValidationException::class);
        StorefrontSettingsData::prepareValidated([
            'site_name' => ['an array'],
            'default_meta_description' => null,
            'search_placeholder' => 'search',
            'footer_brand_text' => 'footer',
        ]);
    }
}
