<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Catalog\Models\Brand;
use App\Filament\Admin\Resources\BrandResource\Pages;
use App\Filament\Admin\Resources\BrandResource\Schemas\BrandForm;
use App\Filament\Admin\Resources\BrandResource\Tables\BrandTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static ?string $recordRouteKeyName = 'public_id';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'Brand';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Brands';
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
        return BrandForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return BrandTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}
