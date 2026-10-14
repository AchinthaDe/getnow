<?php

declare(strict_types=1);

use App\Domain\Content\Actions\GetLayoutContent;
use App\Domain\Content\Settings\StorefrontSettings;
use App\Http\Controllers\Api\V1\Content\GetLayoutContentController;
use Illuminate\Support\Facades\DB;

arch('content api controllers are thin and use GetLayoutContent')
    ->expect(GetLayoutContentController::class)
    ->toUse(GetLayoutContent::class);

arch('content api controllers do not depend on domain models, db, or storefront settings directly')
    ->expect('App\Http\Controllers\Api\V1\Content')
    ->not->toUse([
        'App\Domain\Content\Models',
        DB::class,
        StorefrontSettings::class,
    ]);

arch('content domain does not depend on http or illuminate http')
    ->expect('App\Domain\Content')
    ->not->toUse('Illuminate\Http');
