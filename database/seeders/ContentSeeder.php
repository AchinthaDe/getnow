<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Content\Actions\CreateContentBlock;
use App\Domain\Content\Actions\EnableContentBlock;
use App\Domain\Content\Actions\PublishContentBlock;
use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Actions\UpdateMenu;
use App\Domain\Content\Actions\UpdateStorefrontSettings;
use App\Domain\Content\Data\CreateContentBlockData;
use App\Domain\Content\Data\StorefrontSettingsData;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Data\UpdateMenuData;
use App\Domain\Content\Data\UpdateMenuItemInputData;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\Tone;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

final class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedAnnouncementBar();
        $this->seedMenus();
    }

    private function seedSettings(): void
    {
        $settingsData = new StorefrontSettingsData(
            site_name: 'GetNow',
            default_meta_description: 'Discover headphones, phones, smartwatches and everyday essentials at GetNow. Shop in Sri Lankan rupees.',
            search_placeholder: 'What are you looking for?',
            footer_brand_text: "Thoughtfully chosen tech.\nFor the way you live.",
        );

        app(UpdateStorefrontSettings::class)($settingsData);
    }

    private function seedAnnouncementBar(): void
    {
        $existingBlock = ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)
            ->where('type', 'announcement_bar')
            ->where('status', '!=', PublishStatus::ARCHIVED->value)
            ->orderBy('id')
            ->first();

        $payload = [
            'text' => 'Good tech. Better everyday.',
            'link_url' => null,
            'tone' => Tone::INFO->value,
        ];

        if ($existingBlock === null) {
            $createData = new CreateContentBlockData(
                placement: Placement::ANNOUNCEMENT_BAR,
                type: 'announcement_bar',
                starts_at: null,
                ends_at: null,
            );

            $block = app(CreateContentBlock::class)($createData, $payload);
            app(PublishContentBlock::class)($block->id);
        } else {
            $updateData = new UpdateContentBlockData(
                starts_at: null,
                ends_at: null,
            );

            app(UpdateContentBlock::class)($existingBlock->id, $updateData, $payload);

            if ($existingBlock->status !== PublishStatus::PUBLISHED) {
                app(PublishContentBlock::class)($existingBlock->id);
            }

            if (! $existingBlock->is_enabled) {
                app(EnableContentBlock::class)($existingBlock->id);
            }
        }
    }

    private function seedMenus(): void
    {
        $categoriesChildren = [
            ['label' => 'Headphones & earbuds', 'url' => '/shop?category=audio'],
            ['label' => 'Smartphones', 'url' => '/shop?category=phones'],
            ['label' => 'Smartwatches', 'url' => '/shop?category=wearables'],
            ['label' => 'Speakers', 'url' => '/shop?category=speakers'],
            ['label' => 'Power & charging', 'url' => '/shop?category=charging'],
            ['label' => 'Gaming', 'url' => '/shop?category=gaming'],
            ['label' => 'Smart home', 'url' => '/shop?category=smart-home'],
            ['label' => 'Accessories', 'url' => '/shop?category=accessories'],
        ];

        /** @var array<string, array{title: ?string, items: array<int, array{label: string, url: string, children?: array<int, array{label: string, url: string}>}>}> $menusData */
        $menusData = [
            MenuKey::MAIN_NAV->value => [
                'title' => null,
                'items' => [
                    [
                        'label' => 'Browse categories',
                        'url' => '/shop',
                        'children' => $categoriesChildren,
                    ],
                    ['label' => 'All products', 'url' => '/shop'],
                    ['label' => 'New arrivals', 'url' => '/shop?sort=newest'],
                    ['label' => 'Trending now', 'url' => '/shop?sort=featured'],
                    ['label' => 'The good deals', 'url' => '/shop?deals=1'],
                    ['label' => 'Need a hand?', 'url' => '/help'],
                ],
            ],
            MenuKey::MOBILE_NAV->value => [
                'title' => null,
                'items' => [
                    ['label' => 'Shop everything', 'url' => '/shop'],
                    [
                        'label' => 'Categories',
                        'url' => '/shop',
                        'children' => $categoriesChildren,
                    ],
                    ['label' => 'My account', 'url' => '/account'],
                    ['label' => 'Saved items', 'url' => '/saved'],
                    ['label' => 'Help & delivery', 'url' => '/help'],
                ],
            ],
            MenuKey::FOOTER_COLUMN_1->value => [
                'title' => 'Find your next favourite',
                'items' => [
                    ['label' => 'Headphones & earbuds', 'url' => '/shop?category=audio'],
                    ['label' => 'Smartphones', 'url' => '/shop?category=phones'],
                    ['label' => 'Smartwatches', 'url' => '/shop?category=wearables'],
                    ['label' => 'Shop all products', 'url' => '/shop'],
                ],
            ],
            MenuKey::FOOTER_COLUMN_2->value => [
                'title' => 'A little help',
                'items' => [
                    ['label' => 'Delivery information', 'url' => '/help#delivery'],
                    ['label' => 'Returns & warranty', 'url' => '/help#returns'],
                    ['label' => 'My account', 'url' => '/account'],
                    ['label' => 'Terms & privacy', 'url' => '/terms'],
                ],
            ],
            MenuKey::FOOTER_COLUMN_3->value => [
                'title' => "Small upgrades.\nBig possibilities.",
                'items' => [
                    ['label' => "Discover what's new", 'url' => '/shop?sort=newest'],
                ],
            ],
        ];

        foreach ($menusData as $menuKeyValue => $menuDef) {
            /** @var Menu $menu */
            $menu = Menu::where('key', $menuKeyValue)->firstOrFail();

            /** @var Collection<string, MenuItem> $existingRoots */
            $existingRoots = $menu->items()->whereNull('parent_id')->get()->keyBy('label');

            $inputItems = [];
            foreach ($menuDef['items'] as $itemDef) {
                $existingRoot = $existingRoots->get($itemDef['label']);
                $rootPublicId = $existingRoot?->public_id;

                $existingChildren = $existingRoot !== null
                    ? $menu->items()->where('parent_id', $existingRoot->id)->get()->keyBy('label')
                    : collect();

                $inputChildren = [];
                if (! empty($itemDef['children'])) {
                    foreach ($itemDef['children'] as $childDef) {
                        $existingChild = $existingChildren->get($childDef['label']);
                        $childPublicId = $existingChild?->public_id;

                        $inputChildren[] = new UpdateMenuItemInputData(
                            public_id: $childPublicId,
                            label: $childDef['label'],
                            url: $childDef['url'],
                            icon: null,
                            opens_in_new_tab: false,
                            is_enabled: true,
                            children: [],
                        );
                    }
                }

                $inputItems[] = new UpdateMenuItemInputData(
                    public_id: $rootPublicId,
                    label: $itemDef['label'],
                    url: $itemDef['url'],
                    icon: null,
                    opens_in_new_tab: false,
                    is_enabled: true,
                    children: $inputChildren,
                );
            }

            $updateData = new UpdateMenuData(
                title: $menuDef['title'],
                items: $inputItems,
            );

            app(UpdateMenu::class)($menu, $updateData);
        }
    }
}
