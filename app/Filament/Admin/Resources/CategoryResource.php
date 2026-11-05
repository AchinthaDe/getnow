<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Catalog\Models\Category;
use App\Filament\Admin\Resources\CategoryResource\Pages;
use App\Filament\Admin\Resources\CategoryResource\Schemas\CategoryForm;
use App\Filament\Admin\Resources\CategoryResource\Tables\CategoryTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $recordRouteKeyName = 'public_id';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'Category';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Categories';
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
        return CategoryForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoryTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
