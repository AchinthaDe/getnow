<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BrandResource\Pages;

use App\Domain\Catalog\Actions\Brand\CreateBrand as CreateBrandAction;
use App\Domain\Catalog\Data\Brand\CreateBrandData;
use App\Domain\Catalog\Exceptions\BrandRuleViolation;
use App\Filament\Admin\Resources\BrandResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class CreateBrand extends CreateRecord
{
    protected static string $resource = BrandResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = Filament::auth()->user();
        $causer = $user instanceof User ? $user : null;
        try {
            $actionData = CreateBrandData::validateAndCreate([
                'name' => (string) $data['name'],
                'show_in_nav' => (bool) ($data['show_in_nav'] ?? false),
            ]);

            return app(CreateBrandAction::class)($actionData, $causer);
        } catch (BrandRuleViolation $e) {
            Notification::make()->danger()->title('Cannot Create Brand')->body($e->getMessage())->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
