<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CategoryResource\Pages;

use App\Domain\Catalog\Actions\Category\CreateCategory as CreateCategoryAction;
use App\Domain\Catalog\Data\Category\CreateCategoryData;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Filament\Admin\Resources\CategoryResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = Filament::auth()->user();
        $causer = $user instanceof User ? $user : null;
        try {
            $parentPublicId = filled($data['parent_public_id'] ?? null) ? (string) $data['parent_public_id'] : null;
            $actionData = new CreateCategoryData(
                name: (string) $data['name'],
                parent_public_id: $parentPublicId,
                show_in_nav: (bool) ($data['show_in_nav'] ?? true),
            );

            return app(CreateCategoryAction::class)($actionData, $causer);
        } catch (CategoryRuleViolation $e) {
            Notification::make()->danger()->title('Cannot Create Category')->body($e->getMessage())->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
