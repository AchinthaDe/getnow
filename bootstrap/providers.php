<?php

use App\Providers\AppServiceProvider;
use App\Providers\ContentServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    ContentServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
];
