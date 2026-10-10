<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Pages;

use App\Filament\Admin\Resources\ContentBlockResource;
use Filament\Resources\Pages\ListRecords;

final class ListContentBlocks extends ListRecords
{
    protected static string $resource = ContentBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
