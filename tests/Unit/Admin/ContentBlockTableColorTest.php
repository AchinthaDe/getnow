<?php

declare(strict_types=1);

use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\ServingStatus;
use App\Filament\Admin\Resources\ContentBlockResource\Tables\ContentBlockTable;

it('maps serving status to colors correctly', function (ServingStatus $status) {
    $color = ContentBlockTable::servingStatusColor($status);
    expect($color)->toBeString();
})->with(ServingStatus::cases());

it('maps publish status to colors correctly', function (PublishStatus $status) {
    $color = ContentBlockTable::publishStatusColor($status);
    expect($color)->toBeString();
})->with(PublishStatus::cases());
