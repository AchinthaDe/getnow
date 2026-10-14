<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuResource\Pages;

use App\Domain\Content\Actions\GetMenuTree;
use App\Domain\Content\Actions\UpdateMenu;
use App\Domain\Content\Data\MenuItemData;
use App\Domain\Content\Data\UpdateMenuData;
use App\Domain\Content\Data\UpdateMenuItemInputData;
use App\Domain\Content\Exceptions\InvalidMenuTreeException;
use App\Domain\Content\Models\Menu;
use App\Filament\Admin\Resources\MenuResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Menu $record */
        $record = $this->getRecord();
        $tree = app(GetMenuTree::class)($record);

        return [
            'title' => $tree->title,
            'items' => array_values(array_map(function (MenuItemData $item): array {
                return [
                    'public_id' => $item->public_id,
                    'label' => $item->label,
                    'url' => $item->url,
                    'opens_in_new_tab' => $item->opens_in_new_tab,
                    'is_enabled' => $item->is_enabled,
                    'children' => array_values(array_map(function (MenuItemData $child): array {
                        return [
                            'public_id' => $child->public_id,
                            'label' => $child->label,
                            'url' => $child->url,
                            'opens_in_new_tab' => $child->opens_in_new_tab,
                            'is_enabled' => $child->is_enabled,
                        ];
                    }, $item->children)),
                ];
            }, $tree->items)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var array<int|string, array<string, mixed>> $rawItems */
        $rawItems = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

        $normalizedItems = array_values(array_map(function (array $item): UpdateMenuItemInputData {
            /** @var array<int|string, array<string, mixed>> $rawChildren */
            $rawChildren = isset($item['children']) && is_array($item['children']) ? $item['children'] : [];

            $children = array_values(array_map(function (array $child): UpdateMenuItemInputData {
                return new UpdateMenuItemInputData(
                    public_id: isset($child['public_id']) && is_string($child['public_id']) ? $child['public_id'] : null,
                    label: (string) ($child['label'] ?? ''),
                    url: (string) ($child['url'] ?? ''),
                    opens_in_new_tab: (bool) ($child['opens_in_new_tab'] ?? false),
                    is_enabled: (bool) ($child['is_enabled'] ?? true),
                    children: [],
                );
            }, $rawChildren));

            return new UpdateMenuItemInputData(
                public_id: isset($item['public_id']) && is_string($item['public_id']) ? $item['public_id'] : null,
                label: (string) ($item['label'] ?? ''),
                url: (string) ($item['url'] ?? ''),
                opens_in_new_tab: (bool) ($item['opens_in_new_tab'] ?? false),
                is_enabled: (bool) ($item['is_enabled'] ?? true),
                children: $children,
            );
        }, $rawItems));

        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;

        $updateData = new UpdateMenuData(
            title: $title,
            items: $normalizedItems,
        );

        if (! $record instanceof Menu) {
            throw new \LogicException('Expected Menu model instance.');
        }

        try {
            app(UpdateMenu::class)($record, $updateData, Filament::auth()->user());
        } catch (InvalidMenuTreeException) {
            Notification::make()
                ->danger()
                ->title('Update Failed')
                ->body('The menu structure is invalid and could not be saved.')
                ->send();

            throw new Halt;
        }

        return $record;
    }

    protected function afterSave(): void
    {
        $this->fillForm();
    }
}
