<?php

declare(strict_types=1);

use Spatie\LaravelData\Data;

arch('catalog domain does not depend on filament or http')
    ->expect('App\Domain\Catalog')
    ->not->toUse(['App\Filament', 'App\Http', 'Filament']);

arch('content domain does not depend on catalog models')
    ->expect('App\Domain\Content')
    ->not->toUse('App\Domain\Catalog\Models');

arch('catalog domain does not depend on content')
    ->expect('App\Domain\Catalog')
    ->not->toUse('App\Domain\Content');

arch('catalog actions are final and invokable')
    ->expect('App\Domain\Catalog\Actions')
    ->toBeFinal()
    ->toHaveMethod('__invoke');

arch('catalog data objects extend spatie data')
    ->expect('App\Domain\Catalog\Data')
    ->toExtend(Data::class);

arch('brand model is not used outside catalog domain')
    ->expect('App\Domain\Catalog\Models\Brand')
    ->toOnlyBeUsedIn(['App\Domain\Catalog', 'Database\Factories']);
