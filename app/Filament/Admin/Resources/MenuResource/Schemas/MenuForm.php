<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuResource\Schemas;

use App\Domain\Catalog\Actions\Category\GetCategoryTree;
use App\Domain\Catalog\Enums\CategoryTreeAudience;
use App\Domain\Content\Actions\UpdateMenu;
use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Models\Menu;
use App\Filament\Admin\Resources\MenuResource\Pages\EditMenu;
use App\Support\Rules\NoHtml;
use App\Support\Rules\SafeLink;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class MenuForm
{
    public static function form(Schema $schema): Schema
    {
        $livewireInstance = $schema->getLivewire();

        return $schema
            ->components([
                Section::make('Menu Details')
                    ->schema([
                        TextInput::make('title')
                            ->label('Storefront Title')
                            ->helperText('Displayed as the heading on storefront footer menu columns.')
                            ->maxLength(UpdateMenu::MAX_TITLE_LENGTH)
                            ->rule(new NoHtml)
                            ->nullable(),
                    ]),

                Section::make('Top-Level Menu Items (Level 1)')
                    ->description('Up to '.UpdateMenu::MAX_TOP_LEVEL_ITEMS.' top-level items. Drag to reorder, or click an item to expand its fields and dropdown sub-items.')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->collapsible()
                            ->collapsed()
                            ->collapseAllAction(fn (Action $action): Action => $action->label('Collapse All'))
                            ->expandAllAction(fn (Action $action): Action => $action->label('Expand All'))
                            ->addActionLabel('Add Top-Level Item')
                            ->maxItems(UpdateMenu::MAX_TOP_LEVEL_ITEMS)
                            ->defaultItems(0)
                            ->itemLabel(
                                /** @param array<string, mixed> $state */
                                function (array $state, mixed $livewire = null) use ($livewireInstance): string {
                                    $page = $livewire instanceof EditMenu ? $livewire : ($livewireInstance instanceof EditMenu ? $livewireInstance : null);
                                    $kind = $state['kind'] ?? MenuItemKind::LINK->value;

                                    if ($kind === MenuItemKind::CATEGORY_TREE->value) {
                                        $catPublicId = $state['category_public_id'] ?? null;
                                        $catName = null;
                                        if (filled($catPublicId) && $page !== null) {
                                            $catNode = $page->getCategoriesByPublicId()[(string) $catPublicId] ?? null;
                                            $catName = $catNode?->name;
                                        }
                                        $targetDesc = filled($catName) ? 'Children of '.$catName : 'All Categories';
                                        $disabledPart = (array_key_exists('is_enabled', $state) && ! (bool) $state['is_enabled']) ? '  [Disabled]' : '';

                                        return "Category Tree: {$targetDesc}{$disabledPart}";
                                    }

                                    if ($kind === MenuItemKind::CATEGORY->value) {
                                        $catPublicId = $state['category_public_id'] ?? null;
                                        $catName = null;
                                        if (filled($catPublicId) && $page !== null) {
                                            $catNode = $page->getCategoriesByPublicId()[(string) $catPublicId] ?? null;
                                            $catName = $catNode?->name;
                                        }
                                        $label = filled($state['label'] ?? null)
                                            ? (string) $state['label']
                                            : ($catName !== null ? $catName.' [Category]' : 'New category item');
                                        $urlPart = $catName !== null ? '  —  '.$catName : '';
                                        /** @var array<int|string, mixed> $children */
                                        $children = isset($state['children']) && is_array($state['children']) ? $state['children'] : [];
                                        $childCount = count($children);
                                        $countPart = $childCount > 0 ? '  •  ('.$childCount.' '.($childCount === 1 ? 'sub-item' : 'sub-items').')' : '';
                                        $newTabPart = ! empty($state['opens_in_new_tab']) ? '  [New Tab]' : '';
                                        $disabledPart = (array_key_exists('is_enabled', $state) && ! (bool) $state['is_enabled']) ? '  [Disabled]' : '';

                                        return "{$label}{$urlPart}{$countPart}{$newTabPart}{$disabledPart}";
                                    }

                                    $label = filled($state['label'] ?? null) ? (string) $state['label'] : 'New menu item';
                                    $urlPart = filled($state['url'] ?? null) ? '  —  '.(string) $state['url'] : '';
                                    /** @var array<int|string, mixed> $children */
                                    $children = isset($state['children']) && is_array($state['children']) ? $state['children'] : [];
                                    $childCount = count($children);
                                    $countPart = $childCount > 0 ? '  •  ('.$childCount.' '.($childCount === 1 ? 'sub-item' : 'sub-items').')' : '';
                                    $newTabPart = ! empty($state['opens_in_new_tab']) ? '  [New Tab]' : '';
                                    $disabledPart = (array_key_exists('is_enabled', $state) && ! (bool) $state['is_enabled']) ? '  [Disabled]' : '';

                                    return "{$label}{$urlPart}{$countPart}{$newTabPart}{$disabledPart}";
                                }
                            )
                            ->schema([
                                Hidden::make('public_id'),
                                Grid::make(['default' => 12])
                                    ->schema([
                                        Select::make('kind')
                                            ->label('Kind')
                                            ->options(fn (mixed $livewire = null, mixed $record = null): array => self::getRootKindOptions(
                                                $livewire instanceof EditMenu ? $livewire : ($livewireInstance instanceof EditMenu ? $livewireInstance : null),
                                                $record,
                                            ))
                                            ->default(MenuItemKind::LINK->value)
                                            ->selectablePlaceholder(false)
                                            ->live()
                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                        Select::make('category_public_id')
                                            ->label('Category')
                                            ->options(fn (mixed $livewire = null): array => self::getCategoryOptions(
                                                $livewire instanceof EditMenu ? $livewire : ($livewireInstance instanceof EditMenu ? $livewireInstance : null),
                                            ))
                                            ->nullable()
                                            ->searchable()
                                            ->live()
                                            ->visible(fn (Get $get): bool => in_array($get('kind'), [MenuItemKind::CATEGORY->value, MenuItemKind::CATEGORY_TREE->value], true))
                                            ->required(fn (Get $get): bool => $get('kind') === MenuItemKind::CATEGORY->value)
                                            ->placeholder(fn (Get $get): string => $get('kind') === MenuItemKind::CATEGORY_TREE->value ? 'All categories (whole storefront tree)' : 'Select a category...')
                                            ->helperText(fn (Get $get, mixed $livewire = null): ?string => self::getCategoryWarning(
                                                $get('category_public_id'),
                                                $livewire instanceof EditMenu ? $livewire : ($livewireInstance instanceof EditMenu ? $livewireInstance : null),
                                            ))
                                            ->columnSpan(['default' => 12, 'md' => 8]),
                                        TextInput::make('label')
                                            ->label('Label')
                                            ->maxLength(UpdateMenu::MAX_LABEL_LENGTH)
                                            ->rule(new NoHtml)
                                            ->required(fn (Get $get): bool => ($get('kind') ?? MenuItemKind::LINK->value) === MenuItemKind::LINK->value)
                                            ->hidden(fn (Get $get): bool => $get('kind') === MenuItemKind::CATEGORY_TREE->value)
                                            ->dehydrated(true)
                                            ->placeholder(fn (Get $get): ?string => $get('kind') === MenuItemKind::CATEGORY->value ? 'Optional (defaults to category name)' : null)
                                            ->live(onBlur: true)
                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                        TextInput::make('url')
                                            ->label('URL / Path')
                                            ->maxLength(UpdateMenu::MAX_URL_LENGTH)
                                            ->rule(new SafeLink)
                                            ->required(fn (Get $get): bool => ($get('kind') ?? MenuItemKind::LINK->value) === MenuItemKind::LINK->value)
                                            ->visible(fn (Get $get): bool => ($get('kind') ?? MenuItemKind::LINK->value) === MenuItemKind::LINK->value)
                                            ->live(onBlur: true)
                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                        Toggle::make('opens_in_new_tab')
                                            ->label('New tab')
                                            ->default(false)
                                            ->live()
                                            ->inline(false)
                                            ->hidden(fn (Get $get): bool => $get('kind') === MenuItemKind::CATEGORY_TREE->value)
                                            ->dehydrated(true)
                                            ->columnSpan(['default' => 6, 'md' => 2]),
                                        Toggle::make('is_enabled')
                                            ->label('Enabled')
                                            ->default(true)
                                            ->live()
                                            ->inline(false)
                                            ->columnSpan(['default' => 6, 'md' => 2]),
                                    ]),
                                Section::make('Dropdown Sub-Items (Level 2)')
                                    ->compact()
                                    ->collapsible()
                                    ->collapsed()
                                    ->hidden(fn (Get $get): bool => $get('kind') === MenuItemKind::CATEGORY_TREE->value)
                                    ->description(function (Get $get): string {
                                        /** @var array<int|string, mixed>|null $children */
                                        $children = $get('children');
                                        $count = is_array($children) ? count($children) : 0;

                                        return "{$count} / ".UpdateMenu::MAX_CHILDREN_PER_PARENT.' sub-items';
                                    })
                                    ->schema([
                                        Repeater::make('children')
                                            ->hiddenLabel()
                                            ->collapsible()
                                            ->collapsed()
                                            ->addActionLabel('Add Sub-Item')
                                            ->maxItems(UpdateMenu::MAX_CHILDREN_PER_PARENT)
                                            ->defaultItems(0)
                                            ->itemLabel(
                                                /** @param array<string, mixed> $state */
                                                function (array $state, mixed $livewire = null) use ($livewireInstance): string {
                                                    $page = $livewire instanceof EditMenu ? $livewire : ($livewireInstance instanceof EditMenu ? $livewireInstance : null);
                                                    $kind = $state['kind'] ?? MenuItemKind::LINK->value;

                                                    if ($kind === MenuItemKind::CATEGORY->value) {
                                                        $catPublicId = $state['category_public_id'] ?? null;
                                                        $catName = null;
                                                        if (filled($catPublicId) && $page !== null) {
                                                            $catNode = $page->getCategoriesByPublicId()[(string) $catPublicId] ?? null;
                                                            $catName = $catNode?->name;
                                                        }
                                                        $label = filled($state['label'] ?? null)
                                                            ? (string) $state['label']
                                                            : ($catName !== null ? $catName.' [Category]' : 'New category item');
                                                        $urlPart = $catName !== null ? '  —  '.$catName : '';
                                                        $newTabPart = ! empty($state['opens_in_new_tab']) ? '  [New Tab]' : '';
                                                        $disabledPart = (array_key_exists('is_enabled', $state) && ! (bool) $state['is_enabled']) ? '  [Disabled]' : '';

                                                        return "{$label}{$urlPart}{$newTabPart}{$disabledPart}";
                                                    }

                                                    $label = filled($state['label'] ?? null) ? (string) $state['label'] : 'New sub-item';
                                                    $urlPart = filled($state['url'] ?? null) ? '  —  '.(string) $state['url'] : '';
                                                    $newTabPart = ! empty($state['opens_in_new_tab']) ? '  [New Tab]' : '';
                                                    $disabledPart = (array_key_exists('is_enabled', $state) && ! (bool) $state['is_enabled']) ? '  [Disabled]' : '';

                                                    return "{$label}{$urlPart}{$newTabPart}{$disabledPart}";
                                                }
                                            )
                                            ->schema([
                                                Hidden::make('public_id'),
                                                Grid::make(['default' => 12])
                                                    ->schema([
                                                        Select::make('kind')
                                                            ->label('Kind')
                                                            ->options(fn (mixed $livewire = null, mixed $record = null): array => self::getChildKindOptions(
                                                                $livewire instanceof EditMenu ? $livewire : ($livewireInstance instanceof EditMenu ? $livewireInstance : null),
                                                                $record,
                                                            ))
                                                            ->default(MenuItemKind::LINK->value)
                                                            ->selectablePlaceholder(false)
                                                            ->live()
                                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                                        Select::make('category_public_id')
                                                            ->label('Category')
                                                            ->options(fn (mixed $livewire = null): array => self::getCategoryOptions(
                                                                $livewire instanceof EditMenu ? $livewire : ($livewireInstance instanceof EditMenu ? $livewireInstance : null),
                                                            ))
                                                            ->nullable()
                                                            ->searchable()
                                                            ->live()
                                                            ->visible(fn (Get $get): bool => $get('kind') === MenuItemKind::CATEGORY->value)
                                                            ->required(fn (Get $get): bool => $get('kind') === MenuItemKind::CATEGORY->value)
                                                            ->placeholder('Select a category...')
                                                            ->helperText(fn (Get $get, mixed $livewire = null): ?string => self::getCategoryWarning(
                                                                $get('category_public_id'),
                                                                $livewire instanceof EditMenu ? $livewire : ($livewireInstance instanceof EditMenu ? $livewireInstance : null),
                                                            ))
                                                            ->columnSpan(['default' => 12, 'md' => 8]),
                                                        TextInput::make('label')
                                                            ->label('Label')
                                                            ->maxLength(UpdateMenu::MAX_LABEL_LENGTH)
                                                            ->rule(new NoHtml)
                                                            ->required(fn (Get $get): bool => ($get('kind') ?? MenuItemKind::LINK->value) === MenuItemKind::LINK->value)
                                                            ->placeholder(fn (Get $get): ?string => $get('kind') === MenuItemKind::CATEGORY->value ? 'Optional (defaults to category name)' : null)
                                                            ->live(onBlur: true)
                                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                                        TextInput::make('url')
                                                            ->label('URL / Path')
                                                            ->maxLength(UpdateMenu::MAX_URL_LENGTH)
                                                            ->rule(new SafeLink)
                                                            ->required(fn (Get $get): bool => ($get('kind') ?? MenuItemKind::LINK->value) === MenuItemKind::LINK->value)
                                                            ->visible(fn (Get $get): bool => ($get('kind') ?? MenuItemKind::LINK->value) === MenuItemKind::LINK->value)
                                                            ->live(onBlur: true)
                                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                                        Toggle::make('opens_in_new_tab')
                                                            ->label('New tab')
                                                            ->default(false)
                                                            ->live()
                                                            ->inline(false)
                                                            ->columnSpan(['default' => 6, 'md' => 2]),
                                                        Toggle::make('is_enabled')
                                                            ->label('Enabled')
                                                            ->default(true)
                                                            ->live()
                                                            ->inline(false)
                                                            ->columnSpan(['default' => 6, 'md' => 2]),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function getCategoryOptions(mixed $livewire): array
    {
        if ($livewire instanceof EditMenu) {
            return $livewire->getCategoryOptions();
        }

        /** @var GetCategoryTree $getTree */
        $getTree = app(GetCategoryTree::class);
        $tree = $getTree(CategoryTreeAudience::Admin);
        $options = [];
        $traverse = function (array $nodes, int $depth) use (&$traverse, &$options): void {
            foreach ($nodes as $node) {
                $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
                $options[$node->public_id] = $prefix.$node->name;
                if (! empty($node->children)) {
                    $traverse($node->children, $depth + 1);
                }
            }
        };
        $traverse($tree, 0);

        return $options;
    }

    private static function getCategoryWarning(?string $categoryPublicId, ?EditMenu $livewire): ?string
    {
        if ($livewire === null || ! filled($categoryPublicId)) {
            return null;
        }

        $node = $livewire->getCategoriesByPublicId()[$categoryPublicId] ?? null;
        if ($node === null) {
            return null;
        }

        if (! $node->is_effectively_active || ! $node->show_in_nav) {
            return 'Warning: This category is inactive or hidden from navigation and will be omitted from the storefront layout.';
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function getRootKindOptions(?EditMenu $livewire, mixed $record): array
    {
        $menu = $record instanceof Menu
            ? $record
            : ($livewire !== null && $livewire->getRecord() instanceof Menu ? $livewire->getRecord() : null);

        $allowed = $menu instanceof Menu
            ? $menu->key->allowedKinds()
            : [MenuItemKind::LINK, MenuItemKind::CATEGORY, MenuItemKind::CATEGORY_TREE];

        $options = [];
        foreach ($allowed as $kind) {
            $options[$kind->value] = match ($kind) {
                MenuItemKind::LINK => 'Link',
                MenuItemKind::CATEGORY => 'Category',
                MenuItemKind::CATEGORY_TREE => 'Category Tree',
            };
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function getChildKindOptions(?EditMenu $livewire, mixed $record): array
    {
        $menu = $record instanceof Menu
            ? $record
            : ($livewire !== null && $livewire->getRecord() instanceof Menu ? $livewire->getRecord() : null);

        $allowed = $menu instanceof Menu
            ? $menu->key->allowedKinds()
            : [MenuItemKind::LINK, MenuItemKind::CATEGORY];

        $options = [];
        foreach ($allowed as $kind) {
            if ($kind === MenuItemKind::CATEGORY_TREE) {
                continue;
            }
            $options[$kind->value] = match ($kind) {
                MenuItemKind::LINK => 'Link',
                MenuItemKind::CATEGORY => 'Category',
            };
        }

        return $options;
    }
}
