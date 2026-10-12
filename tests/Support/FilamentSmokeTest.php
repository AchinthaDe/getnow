<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

trait FilamentSmokeTest
{
    public static function getPlatformAdmin(): User
    {
        return User::factory()->platformAdmin()->create();
    }

    public static function assertListPageRenders(string $pageClass): void
    {
        $admin = static::getPlatformAdmin();

        actingAs($admin)
            ->get($pageClass::getUrl())
            ->assertSuccessful();

        Livewire::actingAs($admin)->test($pageClass)->assertSuccessful();
    }

    public static function assertPageRenders(string $pageClass): void
    {
        $admin = static::getPlatformAdmin();

        actingAs($admin)
            ->get($pageClass::getUrl())
            ->assertSuccessful();

        Livewire::actingAs($admin)->test($pageClass)->assertSuccessful();
    }

    public static function assertCreatePageRenders(string $pageClass): void
    {
        $admin = static::getPlatformAdmin();

        actingAs($admin)
            ->get($pageClass::getUrl())
            ->assertSuccessful();

        Livewire::actingAs($admin)->test($pageClass)->assertSuccessful();
    }

    public static function assertEditPageRenders(string $pageClass, Model $record): void
    {
        $admin = static::getPlatformAdmin();

        actingAs($admin)
            ->get($pageClass::getUrl(['record' => $record]))
            ->assertSuccessful();

        /** @var class-string<\Filament\Resources\Resource> $resource */
        $resource = $pageClass::getResource();
        $routeKeyName = $resource::getRecordRouteKeyName() ?? $record->getRouteKeyName();

        Livewire::actingAs($admin)->test($pageClass, ['record' => $record->getAttribute($routeKeyName)])
            ->assertSuccessful();
    }
}
