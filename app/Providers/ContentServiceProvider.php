<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Content\Actions\GetBlockServingStatus;
use App\Domain\Content\Blocks\AnnouncementBar\AnnouncementBarBlock;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Policies\ContentBlockPolicy;
use App\Domain\Content\Services\ContentBlockRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BlockRegistry::class, ContentBlockRegistry::class);
        $this->app->scoped(GetBlockServingStatus::class);
    }

    public function boot(): void
    {
        Gate::policy(ContentBlock::class, ContentBlockPolicy::class);

        /** @var BlockRegistry $registry */
        $registry = $this->app->make(BlockRegistry::class);

        $registry->register(new AnnouncementBarBlock);
    }
}
