<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CategoryResource\Actions;

use App\Domain\Catalog\Actions\Category\ActivateCategory;
use App\Domain\Catalog\Actions\Category\DeactivateCategory;
use App\Domain\Catalog\Actions\Category\DeleteCategory;
use App\Domain\Catalog\Actions\Category\GetCategoryTree;
use App\Domain\Catalog\Actions\Category\MoveCategory;
use App\Domain\Catalog\Actions\Category\ReorderCategories;
use App\Domain\Catalog\Data\Category\CategoryTreeNodeData;
use App\Domain\Catalog\Data\Category\MoveCategoryData;
use App\Domain\Catalog\Data\Category\ReorderCategoriesData;
use App\Domain\Catalog\Enums\CategoryTreeAudience;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Domain\Catalog\Models\Category;
use App\Filament\Admin\Resources\CategoryResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;

final class CategoryTableActions
{
    public static function activate(): Action
    {
        return Action::make('activate')
            ->label('Activate')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Category $record): bool => ! $record->is_active)
            ->authorize(fn (Category $record): bool => (bool) Filament::auth()->user()?->can('update', $record))
            ->action(function (Category $record, Action $action): void {
                $user = Filament::auth()->user();
                $causer = $user instanceof User ? $user : null;
                if ($causer === null) {
                    abort(403);
                }
                abort_unless($causer->can('update', $record), 403);
                try {
                    app(ActivateCategory::class)($record->public_id, $causer);
                    Notification::make()->success()->title('Category Activated')->body("Category [{$record->name}] has been activated.")->send();
                } catch (CategoryRuleViolation $e) {
                    Notification::make()->danger()->title('Activation Failed')->body($e->getMessage())->send();
                    $action->halt();
                }
            });
    }

    public static function deactivate(): Action
    {
        return Action::make('deactivate')
            ->label('Deactivate')
            ->icon('heroicon-o-x-circle')
            ->color('warning')
            ->visible(fn (Category $record): bool => $record->is_active)
            ->requiresConfirmation()
            ->modalHeading('Deactivate Category')
            ->modalDescription('Deactivating this category will also hide its subcategories on the storefront.')
            ->authorize(fn (Category $record): bool => (bool) Filament::auth()->user()?->can('update', $record))
            ->action(function (Category $record, Action $action): void {
                $user = Filament::auth()->user();
                $causer = $user instanceof User ? $user : null;
                if ($causer === null) {
                    abort(403);
                }
                abort_unless($causer->can('update', $record), 403);
                try {
                    app(DeactivateCategory::class)($record->public_id, $causer);
                    Notification::make()->success()->title('Category Deactivated')->body("Category [{$record->name}] has been deactivated.")->send();
                } catch (CategoryRuleViolation $e) {
                    Notification::make()->danger()->title('Deactivation Failed')->body($e->getMessage())->send();
                    $action->halt();
                }
            });
    }

    public static function move(): Action
    {
        return Action::make('move')
            ->label('Move / Reparent')
            ->icon('heroicon-o-arrows-pointing-out')
            ->color('gray')
            ->modalHeading(fn (Category $record): string => "Move Category [{$record->name}]")
            ->authorize(fn (Category $record): bool => (bool) Filament::auth()->user()?->can('update', $record))
            ->schema([
                Select::make('parent_public_id')
                    ->label('New Parent Category')
                    ->placeholder('None (Root Category)')
                    ->options(function (Category $record): array {
                        /** @var array<int, CategoryTreeNodeData> $tree */
                        $tree = app(GetCategoryTree::class)(CategoryTreeAudience::Admin);
                        $options = [];
                        foreach ($tree as $rootNode) {
                            if ($rootNode->public_id !== $record->public_id) {
                                $options[$rootNode->public_id] = $rootNode->name;
                            }
                            foreach ($rootNode->children as $childNode) {
                                if ($childNode->public_id !== $record->public_id) {
                                    $options[$childNode->public_id] = '— '.$childNode->name;
                                }
                            }
                        }

                        return $options;
                    })
                    ->default(fn (Category $record): ?string => $record->parent_id !== null ? Category::whereKey($record->parent_id)->value('public_id') : null)
                    ->nullable(),
            ])
            ->action(function (Category $record, array $data, Action $action): void {
                $user = Filament::auth()->user();
                $causer = $user instanceof User ? $user : null;
                if ($causer === null) {
                    abort(403);
                }
                abort_unless($causer->can('update', $record), 403);
                try {
                    $targetParentPublicId = filled($data['parent_public_id'] ?? null) ? (string) $data['parent_public_id'] : null;
                    app(MoveCategory::class)(
                        $record->public_id,
                        new MoveCategoryData(parent_public_id: $targetParentPublicId),
                        $causer
                    );
                    Notification::make()->success()->title('Category Moved')->body("Category [{$record->name}] has been moved.")->send();
                } catch (CategoryRuleViolation $e) {
                    Notification::make()->danger()->title('Move Failed')->body($e->getMessage())->send();
                    $action->halt();
                }
            });
    }

    public static function reorderChildren(): Action
    {
        return Action::make('reorder_children')
            ->label('Reorder Children')
            ->icon('heroicon-o-bars-arrow-up')
            ->color('gray')
            ->modalHeading(fn (Category $record): string => "Reorder Children of [{$record->name}]")
            ->visible(fn (Category $record): bool => $record->parent_id === null && $record->children->isNotEmpty())
            ->authorize(fn (Category $record): bool => (bool) Filament::auth()->user()?->can('update', $record))
            ->schema([
                Repeater::make('siblings')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(true)
                    ->itemLabel(fn (array $state): string => (string) ($state['name'] ?? ''))
                    ->schema([
                        Hidden::make('public_id'),
                        Hidden::make('name'),
                    ]),
            ])
            ->fillForm(fn (Category $record): array => [
                'siblings' => $record->children
                    ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                    ->values()
                    ->map(fn (Category $c): array => ['public_id' => $c->public_id, 'name' => $c->name])
                    ->all(),
            ])
            ->action(function (Category $record, array $data, Action $action): void {
                $user = Filament::auth()->user();
                $causer = $user instanceof User ? $user : null;
                if ($causer === null) {
                    abort(403);
                }
                abort_unless($causer->can('update', $record), 403);
                /** @var array<int, array<string, mixed>> $siblings */
                $siblings = is_array($data['siblings'] ?? null) ? $data['siblings'] : [];
                $orderedIds = array_values(array_filter(array_map(fn (array $item): string => (string) ($item['public_id'] ?? ''), $siblings)));
                try {
                    app(ReorderCategories::class)(
                        new ReorderCategoriesData(ordered_public_ids: $orderedIds, parent_public_id: $record->public_id),
                        $causer
                    );
                    Notification::make()->success()->title('Children Reordered')->body("Children of [{$record->name}] have been reordered.")->send();
                } catch (CategoryRuleViolation $e) {
                    Notification::make()->danger()->title('Reorder Failed')->body($e->getMessage())->send();
                    $action->halt();
                }
            });
    }

    public static function reorderSiblingsHeaderAction(): Action
    {
        return Action::make('reorder_siblings')
            ->label('Reorder Categories')
            ->icon('heroicon-o-bars-arrow-up')
            ->color('gray')
            ->modalHeading('Reorder Category Siblings')
            ->authorize(fn (): bool => (bool) Filament::auth()->user()?->isAdmin())
            ->schema([
                Select::make('parent_public_id')
                    ->label('Parent Group')
                    ->options(function (): array {
                        /** @var array<string, string> $roots */
                        $roots = Category::whereNull('parent_id')
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->pluck('name', 'public_id')
                            ->all();

                        return ['' => 'Root Categories'] + $roots;
                    })
                    ->default('')
                    ->live()
                    ->afterStateUpdated(function (Select $component, ?string $state, Set $set): void {
                        $query = Category::query();
                        if (filled($state)) {
                            $parent = Category::where('public_id', $state)->first();
                            if ($parent !== null) {
                                $query->where('parent_id', $parent->id);
                            } else {
                                $query->whereRaw('1 = 0');
                            }
                        } else {
                            $query->whereNull('parent_id');
                        }

                        $siblings = $query->orderBy('sort_order')->orderBy('id')->get();
                        $set('siblings', $siblings->map(fn (Category $c): array => [
                            'public_id' => $c->public_id,
                            'name' => $c->name,
                        ])->all());
                    }),
                Repeater::make('siblings')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(true)
                    ->itemLabel(fn (array $state): string => (string) ($state['name'] ?? ''))
                    ->schema([
                        Hidden::make('public_id'),
                        Hidden::make('name'),
                    ]),
            ])
            ->fillForm(function (): array {
                $roots = Category::whereNull('parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                return [
                    'parent_public_id' => '',
                    'siblings' => $roots->map(fn (Category $c): array => [
                        'public_id' => $c->public_id,
                        'name' => $c->name,
                    ])->all(),
                ];
            })
            ->action(function (array $data, Action $action): void {
                $user = Filament::auth()->user();
                $causer = $user instanceof User ? $user : null;
                if ($causer === null || ! $causer->isAdmin()) {
                    abort(403);
                }
                $parentPublicId = filled($data['parent_public_id'] ?? null) ? (string) $data['parent_public_id'] : null;
                /** @var array<int, array<string, mixed>> $siblings */
                $siblings = is_array($data['siblings'] ?? null) ? $data['siblings'] : [];
                $orderedIds = array_values(array_filter(array_map(fn (array $item): string => (string) ($item['public_id'] ?? ''), $siblings)));
                try {
                    app(ReorderCategories::class)(
                        new ReorderCategoriesData(ordered_public_ids: $orderedIds, parent_public_id: $parentPublicId),
                        $causer
                    );
                    Notification::make()->success()->title('Categories Reordered')->body('Category sibling sort order updated successfully.')->send();
                } catch (CategoryRuleViolation $e) {
                    Notification::make()->danger()->title('Reorder Failed')->body($e->getMessage())->send();
                    $action->halt();
                }
            });
    }

    public static function delete(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete Category')
            ->modalDescription('Are you sure you want to delete this category? Only childless categories can be deleted.')
            ->authorize(fn (Category $record): bool => (bool) Filament::auth()->user()?->can('delete', $record))
            ->successRedirectUrl(fn (): string => CategoryResource::getUrl('index'))
            ->action(function (Category $record, Action $action): void {
                $user = Filament::auth()->user();
                $causer = $user instanceof User ? $user : null;
                if ($causer === null) {
                    abort(403);
                }
                abort_unless($causer->can('delete', $record), 403);
                try {
                    app(DeleteCategory::class)($record->public_id, $causer);
                    Notification::make()->success()->title('Category Deleted')->body("Category [{$record->name}] has been deleted.")->send();
                } catch (CategoryRuleViolation $e) {
                    Notification::make()->danger()->title('Delete Failed')->body($e->getMessage())->send();
                    $action->halt();
                }
            });
    }
}
