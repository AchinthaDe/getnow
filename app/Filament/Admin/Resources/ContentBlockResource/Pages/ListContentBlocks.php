<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Pages;

use App\Filament\Admin\Resources\ContentBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListContentBlocks extends ListRecords
{
    protected static string $resource = ContentBlockResource::class;

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

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
