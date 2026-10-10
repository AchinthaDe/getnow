<?php

declare(strict_types=1);

use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\ServingStatus;
use App\Filament\Admin\Resources\ContentBlockResource\Tables\ContentBlockTable;

it('maps serving status to colors correctly', function (ServingStatus $status) {
    $expected = match ($status) {
        ServingStatus::Live => 'success',
        ServingStatus::NotLive => 'gray',
        ServingStatus::PayloadError => 'danger',
    };
    $color = ContentBlockTable::servingStatusColor($status);
    expect($color)->toBe($expected);
})->with(ServingStatus::cases());

it('maps publish status to colors correctly', function (PublishStatus $status) {
    $expected = match ($status) {
        PublishStatus::PUBLISHED => 'success',
        PublishStatus::DRAFT => 'gray',
        PublishStatus::ARCHIVED => 'warning',
    };
    $color = ContentBlockTable::publishStatusColor($status);
    expect($color)->toBe($expected);
})->with(PublishStatus::cases());
