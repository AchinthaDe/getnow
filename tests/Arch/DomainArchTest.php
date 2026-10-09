<?php

declare(strict_types=1);
use App\Domain\Content\Exceptions\ContentException;

arch('domain code does not import filament or http')
    ->expect('App\Domain')
    ->not->toUse(['App\Filament', 'App\Http', 'Filament']);

arch('domain actions are final')
    ->expect('App\Domain\Content\Actions')
    ->toBeFinal();

arch('domain actions are invokable')
    ->expect('App\Domain\Content\Actions')
    ->toHaveMethod('__invoke');

arch('domain exceptions extend ContentException')
    ->expect('App\Domain\Content\Exceptions')
    ->classes()
    ->toExtend(ContentException::class)
    ->ignoring(ContentException::class);
