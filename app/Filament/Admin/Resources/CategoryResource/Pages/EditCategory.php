<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CategoryResource\Pages;

use App\Domain\Catalog\Actions\Category\UpdateCategory as UpdateCategoryAction;
use App\Domain\Catalog\Data\Category\UpdateCategoryData;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Domain\Catalog\Models\Category;
use App\Filament\Admin\Resources\CategoryResource;
use App\Filament\Admin\Resources\CategoryResource\Actions\CategoryTableActions;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CategoryTableActions::activate(),
            CategoryTableActions::deactivate(),
            CategoryTableActions::move(),
            CategoryTableActions::delete(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Category);
        $user = Filament::auth()->user();
        $causer = $user instanceof User ? $user : null;
        try {
            $actionData = new UpdateCategoryData(
                name: isset($data['name']) ? (string) $data['name'] : null,
                show_in_nav: isset($data['show_in_nav']) ? (bool) $data['show_in_nav'] : null,
                sort_order: isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            );

            return app(UpdateCategoryAction::class)($record->public_id, $actionData, $causer);
        } catch (CategoryRuleViolation $e) {
            Notification::make()->danger()->title('Cannot Update Category')->body($e->getMessage())->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
