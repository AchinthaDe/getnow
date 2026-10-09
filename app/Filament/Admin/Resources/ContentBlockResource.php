<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages;
use App\Filament\Admin\Resources\ContentBlockResource\Tables\ContentBlockTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use UnitEnum;

class ContentBlockResource extends Resource
{
    protected static ?string $model = ContentBlock::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'Content Block';
    }

    public static function table(Table $table): Table
    {
        return ContentBlockTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentBlocks::route('/'),
        ];
    }
}
