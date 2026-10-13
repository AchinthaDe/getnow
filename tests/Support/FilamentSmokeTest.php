<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

trait FilamentSmokeTest
{
    protected function getPlatformAdmin(): User
    {
        return User::factory()->platformAdmin()->create();
    }

    protected function assertListPageRenders(string $pageClass): void
    {
        $this->actingAs($this->getPlatformAdmin())
            ->get($pageClass::getUrl())
            ->assertSuccessful();

        Livewire::test($pageClass)->assertSuccessful();
    }

    protected function assertPageRenders(string $pageClass): void
    {
        $this->actingAs($this->getPlatformAdmin())
            ->get($pageClass::getUrl())
            ->assertSuccessful();

        Livewire::test($pageClass)->assertSuccessful();
    }

    protected function assertCreatePageRenders(string $pageClass): void
    {
        $this->actingAs($this->getPlatformAdmin())
            ->get($pageClass::getUrl())
            ->assertSuccessful();

        Livewire::test($pageClass)->assertSuccessful();
    }

    protected function assertEditPageRenders(string $pageClass, Model $record): void
    {
        $this->actingAs($this->getPlatformAdmin())
            ->get($pageClass::getUrl(['record' => $record]))
            ->assertSuccessful();

        /** @var class-string<\Filament\Resources\Resource> $resource */
        $resource = $pageClass::getResource();
        $routeKeyName = $resource::getRecordRouteKeyName() ?? $record->getRouteKeyName();

        Livewire::test($pageClass, ['record' => $record->getAttribute($routeKeyName)])
            ->assertSuccessful();
    }

    protected function assertViewPageRenders(string $pageClass, Model $record): void
    {
        $admin = $this->getPlatformAdmin();

        $url = $pageClass::getUrl(['record' => $record]);
        expect($url)->toContain((string) $record->getAttribute('public_id'));

        $this->actingAs($admin)
            ->get($url)
            ->assertSuccessful();

        /** @var class-string<\Filament\Resources\Resource> $resource */
        $resource = $pageClass::getResource();
        $routeKeyName = $resource::getRecordRouteKeyName() ?? $record->getRouteKeyName();

        Livewire::actingAs($admin)
            ->test($pageClass, ['record' => $record->getAttribute($routeKeyName)])
            ->assertSuccessful();
    }
}
