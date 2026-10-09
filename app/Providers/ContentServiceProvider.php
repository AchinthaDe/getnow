<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Content\Blocks\AnnouncementBar\AnnouncementBarBlock;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Services\ContentBlockRegistry;
use Illuminate\Support\ServiceProvider;

class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockRegistry::class, ContentBlockRegistry::class);
    }

    public function boot(): void
    {
        /** @var BlockRegistry $registry */
        $registry = $this->app->make(BlockRegistry::class);

        $registry->register(new AnnouncementBarBlock);
    }
}
