<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuResource\Schemas;

use App\Domain\Content\Actions\UpdateMenu;
use App\Support\Rules\NoHtml;
use App\Support\Rules\SafeLink;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
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
                                function (array $state): string {
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
                                        TextInput::make('label')
                                            ->label('Label')
                                            ->required()
                                            ->maxLength(UpdateMenu::MAX_LABEL_LENGTH)
                                            ->rule(new NoHtml)
                                            ->live(onBlur: true)
                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                        TextInput::make('url')
                                            ->label('URL / Path')
                                            ->required()
                                            ->maxLength(UpdateMenu::MAX_URL_LENGTH)
                                            ->rule(new SafeLink)
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
                                Section::make('Dropdown Sub-Items (Level 2)')
                                    ->compact()
                                    ->collapsible()
                                    ->collapsed()
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
                                                function (array $state): string {
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
                                                        TextInput::make('label')
                                                            ->label('Label')
                                                            ->required()
                                                            ->maxLength(UpdateMenu::MAX_LABEL_LENGTH)
                                                            ->rule(new NoHtml)
                                                            ->live(onBlur: true)
                                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                                        TextInput::make('url')
                                                            ->label('URL / Path')
                                                            ->required()
                                                            ->maxLength(UpdateMenu::MAX_URL_LENGTH)
                                                            ->rule(new SafeLink)
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
}
