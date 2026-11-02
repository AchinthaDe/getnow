<?php

declare(strict_types=1);

use App\Providers\AccountsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ContentServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TokenServiceProvider;

return [
    AppServiceProvider::class,
    AccountsServiceProvider::class,
    ContentServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
    TokenServiceProvider::class,
];
