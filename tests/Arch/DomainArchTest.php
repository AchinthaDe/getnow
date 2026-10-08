<?php

declare(strict_types=1);

arch('domain code does not import filament or http')
    ->expect('App\Domain')
    ->not->toUse(['App\Filament', 'App\Http']);

arch('domain actions are final')
    ->expect('App\Domain\Content\Actions')
    ->toBeFinal();

arch('domain actions are invokable')
    ->expect('App\Domain\Content\Actions')
    ->toHaveMethod('__invoke');
