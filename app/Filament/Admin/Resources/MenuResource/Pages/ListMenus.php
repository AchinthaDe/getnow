<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuResource\Pages;

use App\Filament\Admin\Resources\MenuResource;
use Filament\Resources\Pages\ListRecords;

final class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, array{history: bool, as: string, keep: bool}>
     */
    public function queryStringHandlesPagination(): array
    {
        $queryString = [];
        foreach ($this->paginators as $pageName => $page) {
            $queryString['paginators.'.$pageName] = [
                'history' => false,
                'as' => (string) $pageName,
                'keep' => false,
            ];
        }

        return $queryString;
    }
}
