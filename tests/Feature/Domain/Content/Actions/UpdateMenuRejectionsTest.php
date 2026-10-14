<?php

declare(strict_types=1);

use App\Domain\Content\Actions\UpdateMenu;
use App\Domain\Content\Data\UpdateMenuData;
use App\Domain\Content\Data\UpdateMenuItemInputData;
use App\Domain\Content\Exceptions\InvalidMenuTreeException;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Models\User;

it('rejects link violations with InvalidMenuTreeException', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::firstOrFail();

    $invalidUrls = [
        'javascript:alert(1)',
        'JaVaScRiPt:alert(1)',
        '//evil.com',
        '/\\evil.com',
        'http://insecure.com',
        'data:text/html,test',
        'mailto:user@example.com',
        'https://example.com/'.str_repeat('a', 2040), // > 2048 chars
    ];

    foreach ($invalidUrls as $invalidUrl) {
        $updateData = new UpdateMenuData(
            title: 'Test',
            items: [
                new UpdateMenuItemInputData(
                    public_id: null,
                    label: 'Test Link',
                    url: $invalidUrl,
                ),
            ],
        );

        expect(fn () => app(UpdateMenu::class)($menu, $updateData, $admin))
            ->toThrow(InvalidMenuTreeException::class);
    }
});

it('rejects structure violations and unknown public IDs with InvalidMenuTreeException', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::firstOrFail();
    $otherMenu = Menu::where('id', '!=', $menu->id)->firstOrFail();

    // 1. Foreign public ID (belongs to different menu)
    $foreignItem = MenuItem::factory()->forMenu($otherMenu)->create();
    $dataForeign = new UpdateMenuData(
        title: null,
        items: [
            new UpdateMenuItemInputData(
                public_id: $foreignItem->public_id,
                label: 'Foreign Item',
                url: '/foreign',
            ),
        ],
    );
    expect(fn () => app(UpdateMenu::class)($menu, $dataForeign, $admin))
        ->toThrow(InvalidMenuTreeException::class);

    // 2. Fabricated unknown public ID
    $dataUnknown = new UpdateMenuData(
        title: null,
        items: [
            new UpdateMenuItemInputData(
                public_id: '01JFAKEUNKNOWNPUBLICID0000',
                label: 'Unknown Item',
                url: '/unknown',
            ),
        ],
    );
    expect(fn () => app(UpdateMenu::class)($menu, $dataUnknown, $admin))
        ->toThrow(InvalidMenuTreeException::class);

    // 3. Depth > 2 (child with children)
    $dataDepth = new UpdateMenuData(
        title: null,
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Root',
                url: '/root',
                children: [
                    new UpdateMenuItemInputData(
                        public_id: null,
                        label: 'Child',
                        url: '/child',
                        children: [
                            new UpdateMenuItemInputData(
                                public_id: null,
                                label: 'Grandchild',
                                url: '/grandchild',
                            ),
                        ],
                    ),
                ],
            ),
        ],
    );
    expect(fn () => app(UpdateMenu::class)($menu, $dataDepth, $admin))
        ->toThrow(InvalidMenuTreeException::class);

    // 4. Top-level caps exceeded (> 10)
    $items11 = [];
    for ($i = 0; $i < 11; $i++) {
        $items11[] = new UpdateMenuItemInputData(
            public_id: null,
            label: "Item {$i}",
            url: "/item-{$i}",
        );
    }
    $dataCapsTop = new UpdateMenuData(title: null, items: $items11);
    expect(fn () => app(UpdateMenu::class)($menu, $dataCapsTop, $admin))
        ->toThrow(InvalidMenuTreeException::class);

    // 5. Children caps exceeded (> 10 per parent)
    $children11 = [];
    for ($j = 0; $j < 11; $j++) {
        $children11[] = new UpdateMenuItemInputData(
            public_id: null,
            label: "Child {$j}",
            url: "/child-{$j}",
        );
    }
    $dataCapsChild = new UpdateMenuData(
        title: null,
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Parent',
                url: '/parent',
                children: $children11,
            ),
        ],
    );
    expect(fn () => app(UpdateMenu::class)($menu, $dataCapsChild, $admin))
        ->toThrow(InvalidMenuTreeException::class);

    // 6. Non-null icon rejected
    $dataIcon = new UpdateMenuData(
        title: null,
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Icon Item',
                url: '/icon',
                icon: 'heroicon-o-home',
            ),
        ],
    );
    expect(fn () => app(UpdateMenu::class)($menu, $dataIcon, $admin))
        ->toThrow(InvalidMenuTreeException::class);

    // 7. Duplicate public_id in submission
    $ownItem = MenuItem::factory()->forMenu($menu)->create();
    $dataDuplicate = new UpdateMenuData(
        title: null,
        items: [
            new UpdateMenuItemInputData(
                public_id: $ownItem->public_id,
                label: 'First Entry',
                url: '/entry-1',
            ),
            new UpdateMenuItemInputData(
                public_id: $ownItem->public_id,
                label: 'Second Entry Duplicate',
                url: '/entry-2',
            ),
        ],
    );
    expect(fn () => app(UpdateMenu::class)($menu, $dataDuplicate, $admin))
        ->toThrow(InvalidMenuTreeException::class);
});

it('rejects title exceeding maximum length with InvalidMenuTreeException', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::firstOrFail();

    $tooLongTitle = str_repeat('a', 256);
    $updateData = new UpdateMenuData(
        title: $tooLongTitle,
        items: [],
    );

    expect(fn () => app(UpdateMenu::class)($menu, $updateData, $admin))
        ->toThrow(InvalidMenuTreeException::class);
});
