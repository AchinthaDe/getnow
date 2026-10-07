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

        Livewire::test($pageClass, ['record' => $record->getRouteKey()])
            ->assertSuccessful();
    }
}
