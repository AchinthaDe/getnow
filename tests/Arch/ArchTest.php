<?php

declare(strict_types=1);

arch('domain does not depend on filament or http')
    ->expect('App\Domain')
    ->not->toUse(['App\Filament', 'App\Http']);

arch('strict types are used everywhere')
    ->expect('App')
    ->toUseStrictTypes();
