<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages;
use App\Filament\Admin\Resources\ContentBlockResource\Schemas\ContentBlockForm;
use App\Filament\Admin\Resources\ContentBlockResource\Tables\ContentBlockTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class ContentBlockResource extends Resource
{
    protected static ?string $model = ContentBlock::class;

    protected static ?string $recordRouteKeyName = 'public_id';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'Content Block';
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function getUrl(
        ?string $name = null,
        array $parameters = [],
        bool $isAbsolute = true,
        ?string $panel = null,
        ?Model $tenant = null,
        bool $shouldGuessMissingParameters = false,
        ?string $configuration = null,
    ): string {
        if (isset($parameters['record']) && $parameters['record'] instanceof Model) {
            $routeKeyName = self::getRecordRouteKeyName() ?? $parameters['record']->getRouteKeyName();
            $parameters['record'] = $parameters['record']->getAttribute($routeKeyName);
        }

        return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters, $configuration);
    }

    public static function form(Schema $schema): Schema
    {
        return ContentBlockForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentBlockTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentBlocks::route('/'),
            'create' => Pages\CreateContentBlock::route('/create'),
            'view' => Pages\ViewContentBlock::route('/{record}'),
            'edit' => Pages\EditContentBlock::route('/{record}/edit'),
        ];
    }
}
