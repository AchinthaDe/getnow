<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Pages;

use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PlacementPage;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $homePlacements = collect(Placement::forPage(PlacementPage::HOME))->map(fn (Placement $p) => $p->value)->all();
        $globalPlacements = collect(Placement::forPage(PlacementPage::GLOBAL_LAYOUT))->map(fn (Placement $p) => $p->value)->all();

        return [
            'all' => Tab::make('All Pages')
                ->badge(ContentBlock::query()->count()),
            'home' => Tab::make('Homepage')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('placement', $homePlacements))
                ->badge(ContentBlock::query()->whereIn('placement', $homePlacements)->count()),
            'global_layout' => Tab::make('Global Layout')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('placement', $globalPlacements))
                ->badge(ContentBlock::query()->whereIn('placement', $globalPlacements)->count()),
        ];
    }
}
