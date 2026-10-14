<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Content\Models\Menu;
use App\Filament\Admin\Resources\MenuResource\Pages;
use App\Filament\Admin\Resources\MenuResource\Schemas\MenuForm;
use App\Filament\Admin\Resources\MenuResource\Tables\MenuTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static ?string $recordRouteKeyName = 'public_id';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'Menu';
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
        return MenuForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return MenuTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
