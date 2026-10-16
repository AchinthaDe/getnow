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
use App\Domain\Content\Enums\PromoTheme;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\ServiceIcon;
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
        $this->seedContentBlocks();
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

    private function seedContentBlocks(): void
    {
        $this->seedSingleBlock(
            placement: Placement::ANNOUNCEMENT_BAR,
            type: 'announcement_bar',
            payload: [
                'text' => 'Good tech. Better everyday.',
                'secondary_text' => 'Free delivery on orders over Rs. 25,000',
                'locale_text' => 'Sri Lanka / LKR',
                'link_url' => null,
                'tone' => Tone::INFO->value,
            ],
        );

        $this->seedMultiBlock(
            placement: Placement::HOME_HERO_CAROUSEL,
            type: 'promo_slide',
            items: [
                [
                    'theme' => PromoTheme::SKY->value,
                    'eyebrow' => 'FIND YOUR FREQUENCY',
                    'title_line_1' => 'Less noise.',
                    'title_line_2' => 'More you.',
                    'description' => 'Make room for better sound. Explore offers on everyday audio.',
                    'cta_label' => 'Shop audio offers',
                    'cta_url' => '/shop?category=audio&deals=1',
                    'image_url' => '/images/products/soundcore-space-2.jpg',
                    'image_alt' => 'Soundcore Space 2 over-ear headphones',
                    'label' => 'Audio offers',
                ],
                [
                    'theme' => PromoTheme::LILAC->value,
                    'eyebrow' => 'THE NEXT CHAPTER',
                    'title_line_1' => 'Your next',
                    'title_line_2' => 'big thing.',
                    'description' => 'A fresh perspective in your pocket. Find your next everyday upgrade.',
                    'cta_label' => 'Explore phones',
                    'cta_url' => '/shop?category=phones',
                    'image_url' => '/images/products/galaxy-s26-ultra.webp',
                    'image_alt' => 'Samsung Galaxy S26 Ultra smartphone',
                    'label' => 'The phone edit',
                ],
                [
                    'theme' => PromoTheme::MINT->value,
                    'eyebrow' => 'READY FOR EVERY DAY',
                    'title_line_1' => 'Small kit.',
                    'title_line_2' => 'Big energy.',
                    'description' => 'Keep the good things going. Discover offers on charging essentials.',
                    'cta_label' => 'Shop charging offers',
                    'cta_url' => '/shop?category=charging&deals=1',
                    'image_url' => '/images/products/ugreen-uno-65w.png',
                    'image_alt' => 'UGREEN Uno 65W charger',
                    'label' => 'Charging offers',
                ],
            ],
        );

        $this->seedSingleBlock(
            placement: Placement::HOME_HERO_SIDE,
            type: 'feature_banner',
            payload: [
                'eyebrow' => 'MAKE ROOM FOR GREAT SOUND',
                'title_line_1' => 'Big sound.',
                'title_line_2' => 'Small footprint.',
                'description' => null,
                'cta_label' => 'Explore speakers',
                'cta_url' => '/shop?category=speakers',
                'image_url' => '/images/products/marshall-acton-iii.png',
                'image_alt' => 'Marshall Acton III speaker',
                'badge_text' => 'MARSHALL ACTON III',
            ],
        );

        $this->seedMultiBlock(
            placement: Placement::HOME_SERVICE_STRIP,
            type: 'service_item',
            items: [
                [
                    'icon' => ServiceIcon::TRUCK->value,
                    'title' => 'Islandwide delivery',
                    'subtitle' => 'From our store to your door',
                    'link_url' => null,
                ],
                [
                    'icon' => ServiceIcon::BOX->value,
                    'title' => 'Cash on delivery',
                    'subtitle' => 'Pay when your order arrives',
                    'link_url' => null,
                ],
                [
                    'icon' => ServiceIcon::SHIELD->value,
                    'title' => 'Shop with confidence',
                    'subtitle' => 'Warranty details on every item',
                    'link_url' => null,
                ],
                [
                    'icon' => ServiceIcon::HEADPHONES->value,
                    'title' => 'Here to help',
                    'subtitle' => 'A little guidance goes a long way',
                    'link_url' => '/help',
                ],
            ],
        );

        $this->seedSingleBlock(
            placement: Placement::HOME_CATEGORIES_HEADER,
            type: 'section_header',
            payload: [
                'eyebrow' => 'FIND YOUR THING',
                'title' => 'A little something for every day.',
                'cta_label' => 'Explore all categories',
                'cta_url' => '/shop',
            ],
        );

        $this->seedSingleBlock(
            placement: Placement::HOME_FEATURED_HEADER,
            type: 'section_header',
            payload: [
                'eyebrow' => 'THE ONES TO KNOW',
                'title' => 'Your next favourites.',
                'cta_label' => 'Shop the collection',
                'cta_url' => '/shop?sort=featured',
            ],
        );

        $this->seedSingleBlock(
            placement: Placement::HOME_COLLECTION_BANNER,
            type: 'feature_banner',
            payload: [
                'eyebrow' => 'POWER YOUR EVERYDAY',
                'title_line_1' => 'Low battery?',
                'title_line_2' => 'Not your energy.',
                'description' => 'Power banks, chargers, and clever little essentials. Ready for wherever the day takes you.',
                'cta_label' => 'Stay charged',
                'cta_url' => '/shop?category=charging',
                'image_url' => '/images/products/shargeek-300.jpg',
                'image_alt' => 'Shargeek 300 power bank',
                'badge_text' => '24/7',
            ],
        );

        $this->seedSingleBlock(
            placement: Placement::HOME_NEW_ARRIVALS_HEADER,
            type: 'section_header',
            payload: [
                'eyebrow' => 'FRESH ON THE SHELF',
                'title' => 'New things. New possibilities.',
                'cta_label' => "See what's new",
                'cta_url' => '/shop?sort=newest',
            ],
        );

        $this->seedSingleBlock(
            placement: Placement::HOME_BRAND_STRIP,
            type: 'brand_strip',
            payload: [
                'eyebrow' => 'GOOD COMPANY',
                'title' => 'Great brands.',
                'brands' => [
                    ['label' => 'Apple', 'url' => '/shop?brand=Apple'],
                    ['label' => 'Samsung', 'url' => '/shop?brand=Samsung'],
                    ['label' => 'Anker', 'url' => '/shop?brand=Anker'],
                    ['label' => 'JBL', 'url' => '/shop?brand=JBL'],
                    ['label' => 'Marshall', 'url' => '/shop?brand=Marshall'],
                    ['label' => 'Sony', 'url' => '/shop?brand=Sony'],
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function seedSingleBlock(Placement $placement, string $type, array $payload): void
    {
        $existingBlock = ContentBlock::where('placement', $placement->value)
            ->where('type', $type)
            ->where('status', '!=', PublishStatus::ARCHIVED->value)
            ->orderBy('id')
            ->first();

        if ($existingBlock === null) {
            $createData = new CreateContentBlockData(
                placement: $placement,
                type: $type,
                starts_at: null,
                ends_at: null,
                sort_order: 0,
            );

            $block = app(CreateContentBlock::class)($createData, $payload);
            app(PublishContentBlock::class)($block->id);
        } else {
            $updateData = new UpdateContentBlockData(
                starts_at: null,
                ends_at: null,
                sort_order: 0,
            );

            app(UpdateContentBlock::class)($existingBlock->id, $updateData, $payload);

            $fresh = $existingBlock->fresh();
            if ($fresh !== null) {
                if ($fresh->status !== PublishStatus::PUBLISHED) {
                    $fresh = app(PublishContentBlock::class)($fresh->id);
                }

                if (! $fresh->is_enabled) {
                    app(EnableContentBlock::class)($fresh->id);
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function seedMultiBlock(Placement $placement, string $type, array $items): void
    {
        $hasExisting = ContentBlock::where('placement', $placement->value)
            ->where('status', '!=', PublishStatus::ARCHIVED->value)
            ->exists();

        if ($hasExisting) {
            return;
        }

        foreach ($items as $index => $payload) {
            $createData = new CreateContentBlockData(
                placement: $placement,
                type: $type,
                starts_at: null,
                ends_at: null,
                sort_order: $index,
            );

            $block = app(CreateContentBlock::class)($createData, $payload);
            app(PublishContentBlock::class)($block->id);
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
