<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CategoryResource\Pages;

use App\Filament\Admin\Resources\CategoryResource;
use App\Filament\Admin\Resources\CategoryResource\Actions\CategoryTableActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            CategoryTableActions::reorderSiblingsHeaderAction(),
        ];
    }
}
