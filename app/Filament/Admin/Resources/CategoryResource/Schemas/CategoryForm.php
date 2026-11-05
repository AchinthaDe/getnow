<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CategoryResource\Schemas;

use App\Domain\Catalog\Actions\Category\GetCategoryTree;
use App\Domain\Catalog\Data\Category\CategoryTreeNodeData;
use App\Domain\Catalog\Enums\CategoryTreeAudience;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CategoryForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->minLength(2)
                            ->maxLength(80),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->helperText('Category slug is generated automatically and is immutable (Rule CT3).'),
                        Select::make('parent_public_id')
                            ->label('Parent Category')
                            ->placeholder('None (Root Category)')
                            ->options(function (): array {
                                /** @var array<int, CategoryTreeNodeData> $tree */
                                $tree = app(GetCategoryTree::class)(CategoryTreeAudience::Admin);
                                $options = [];
                                foreach ($tree as $rootNode) {
                                    $options[$rootNode->public_id] = $rootNode->name;
                                    foreach ($rootNode->children as $childNode) {
                                        $options[$childNode->public_id] = '— '.$childNode->name;
                                    }
                                }

                                return $options;
                            })
                            ->nullable()
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        Toggle::make('show_in_nav')
                            ->label('Show in Navigation')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->helperText('Sequential sibling sort order. Reordering groups is also available via sibling reorder.'),
                    ]),
            ]);
    }
}
