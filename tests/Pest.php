<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestBlocks;
use Tests\TestCase;

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

uses()->beforeEach(function () {
    TestBlocks::register();
})->in('Feature/Filament', 'Feature/Admin');
