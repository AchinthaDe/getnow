<?php

declare(strict_types=1);

use App\Domain\Catalog\Data\Category\CategoryTreeNodeData;
use App\Domain\Content\Actions\ProjectMenuLayoutItems;
use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use Database\Factories\MenuItemFactory;

it('projects link items preserving public_id, label, url, icon, new_tab, and child items with top-level count of one', function (): void {
    $root = MenuItem::factory()->make([
        'kind' => MenuItemKind::LINK,
        'label' => 'Root Link',
        'url' => '/root',
        'icon' => 'phone',
        'opens_in_new_tab' => true,
    ]);

    $child = MenuItem::factory()->make([
        'parent_id' => 1,
        'kind' => MenuItemKind::LINK,
        'label' => 'Child Link',
        'url' => '/child',
        'icon' => 'tag',
        'opens_in_new_tab' => false,
    ]);

    $root->setRelation('children', collect([$child]));

    $items = (new ProjectMenuLayoutItems)([$root], [], []);

    expect($items)->toHaveCount(1);
    expect($items[0]->id)->toBe($root->public_id);
    expect($items[0]->label)->toBe('Root Link');
    expect($items[0]->url)->toBe('/root');
    expect($items[0]->icon)->toBe('phone');
    expect($items[0]->new_tab)->toBeTrue();
    expect($items[0]->children)->toHaveCount(1);

    expect($items[0]->children[0]->id)->toBe($child->public_id);
    expect($items[0]->children[0]->label)->toBe('Child Link');
    expect($items[0]->children[0]->url)->toBe('/child');
    expect($items[0]->children[0]->icon)->toBe('tag');
    expect($items[0]->children[0]->new_tab)->toBeFalse();
    expect($items[0]->children[0]->children)->toBeEmpty();
});

it('omits a link item when its url or label is null', function (): void {
    $item1 = MenuItem::factory()->make([
        'kind' => MenuItemKind::LINK,
        'label' => 'Valid',
        'url' => null,
    ]);
    $item1->setRelation('children', collect());

    $item2 = MenuItem::factory()->make([
        'kind' => MenuItemKind::LINK,
        'label' => null,
        'url' => '/valid',
    ]);
    $item2->setRelation('children', collect());

    $items = (new ProjectMenuLayoutItems)([$item1, $item2], [], []);

    expect($items)->toBeEmpty();
});

it('projects category items using menu item public_id, derived url, and stored label override', function (): void {
    $catNode = new CategoryTreeNodeData(
        public_id: 'cat_01',
        name: 'Smartphones',
        slug: 'smartphones',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );

    $item = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => 'cat_01',
        'label' => 'Custom Label',
        'url' => null,
        'icon' => 'star',
        'opens_in_new_tab' => true,
    ]);
    $item->setRelation('children', collect());

    $items = (new ProjectMenuLayoutItems)([$item], [$catNode], ['cat_01' => $catNode]);

    expect($items)->toHaveCount(1);
    expect($items[0]->id)->toBe($item->public_id);
    expect($items[0]->label)->toBe('Custom Label');
    expect($items[0]->url)->toBe('/shop?category=smartphones');
    expect($items[0]->icon)->toBe('star');
    expect($items[0]->new_tab)->toBeTrue();
});

it('projects category items preserving empty string label without falling back to category name', function (): void {
    $catNode = new CategoryTreeNodeData(
        public_id: 'cat_01',
        name: 'Smartphones',
        slug: 'smartphones',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );

    $item = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => 'cat_01',
        'label' => '',
        'url' => null,
    ]);
    $item->setRelation('children', collect());

    $items = (new ProjectMenuLayoutItems)([$item], [$catNode], ['cat_01' => $catNode]);

    expect($items)->toHaveCount(1);
    expect($items[0]->label)->toBe('');
});

it('projects category items falling back to category name when stored label is null', function (): void {
    $catNode = new CategoryTreeNodeData(
        public_id: 'cat_01',
        name: 'Smartphones',
        slug: 'smartphones',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );

    $item = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => 'cat_01',
        'label' => null,
        'url' => null,
    ]);
    $item->setRelation('children', collect());

    $items = (new ProjectMenuLayoutItems)([$item], [$catNode], ['cat_01' => $catNode]);

    expect($items)->toHaveCount(1);
    expect($items[0]->label)->toBe('Smartphones');
    expect($items[0]->url)->toBe('/shop?category=smartphones');
});

it('projects category items targeting a subcategory with derived url and menu item public_id', function (): void {
    $subCatNode = new CategoryTreeNodeData(
        public_id: 'cat_sub_01',
        name: 'iPhones',
        slug: 'iphones',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );

    $rootCatNode = new CategoryTreeNodeData(
        public_id: 'cat_root_01',
        name: 'Smartphones',
        slug: 'smartphones',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [$subCatNode],
    );

    $item = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => 'cat_sub_01',
        'label' => null,
        'url' => null,
    ]);
    $item->setRelation('children', collect());

    $items = (new ProjectMenuLayoutItems)(
        [$item],
        [$rootCatNode],
        ['cat_root_01' => $rootCatNode, 'cat_sub_01' => $subCatNode]
    );

    expect($items)->toHaveCount(1);
    expect($items[0]->id)->toBe($item->public_id);
    expect($items[0]->label)->toBe('iPhones');
    expect($items[0]->url)->toBe('/shop?category=iphones');
});

it('projects category items preserving empty children array even when category has taxonomy children', function (): void {
    $subCat1 = new CategoryTreeNodeData(
        public_id: 'cat_sub_1',
        name: 'Sub 1',
        slug: 'sub-1',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );
    $subCat2 = new CategoryTreeNodeData(
        public_id: 'cat_sub_2',
        name: 'Sub 2',
        slug: 'sub-2',
        sort_order: 1,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );
    $parentCat = new CategoryTreeNodeData(
        public_id: 'cat_parent',
        name: 'Parent',
        slug: 'parent',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [$subCat1, $subCat2],
    );

    $item = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => 'cat_parent',
        'label' => null,
        'url' => null,
    ]);
    $item->setRelation('children', collect());

    $items = (new ProjectMenuLayoutItems)(
        [$item],
        [$parentCat],
        ['cat_parent' => $parentCat, 'cat_sub_1' => $subCat1, 'cat_sub_2' => $subCat2]
    );

    expect($items)->toHaveCount(1);
    expect($items[0]->children)->toBeEmpty();
});

it('projects a depth-2 category item under a root item with children: []', function (): void {
    $catNode = new CategoryTreeNodeData(
        public_id: 'cat_01',
        name: 'Tablets',
        slug: 'tablets',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [
            new CategoryTreeNodeData(
                public_id: 'cat_02',
                name: 'iPads',
                slug: 'ipads',
                sort_order: 0,
                show_in_nav: true,
                is_active: true,
                is_effectively_active: true,
                children: [],
            ),
        ],
    );

    $root = MenuItem::factory()->make([
        'kind' => MenuItemKind::LINK,
        'label' => 'Shop',
        'url' => '/shop',
    ]);

    $childCategoryItem = MenuItem::factory()->make([
        'parent_id' => 1,
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => 'cat_01',
        'label' => null,
        'url' => null,
    ]);

    $root->setRelation('children', collect([$childCategoryItem]));

    $items = (new ProjectMenuLayoutItems)([$root], [$catNode], ['cat_01' => $catNode]);

    expect($items)->toHaveCount(1);
    expect($items[0]->children)->toHaveCount(1);
    expect($items[0]->children[0]->children)->toBeEmpty();
});

it('omits category items and their stored children when target category is not in storefront tree', function (): void {
    $root = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => 'missing_cat',
        'label' => 'Missing',
        'url' => null,
    ]);

    $child = MenuItem::factory()->make([
        'parent_id' => 1,
        'kind' => MenuItemKind::LINK,
        'label' => 'Child',
        'url' => '/child',
    ]);

    $root->setRelation('children', collect([$child]));

    $items = (new ProjectMenuLayoutItems)([$root], [], []);

    expect($items)->toBeEmpty();
});

it('preserves visible stored children of a category item while dropping hidden stored children', function (): void {
    $parentCat = new CategoryTreeNodeData(
        public_id: 'cat_parent',
        name: 'Parent',
        slug: 'parent',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );

    $root = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => 'cat_parent',
        'label' => null,
        'url' => null,
    ]);

    $childLink = MenuItem::factory()->make([
        'parent_id' => 1,
        'kind' => MenuItemKind::LINK,
        'label' => 'Visible Link',
        'url' => '/visible',
    ]);

    $childHiddenCat = MenuItem::factory()->make([
        'parent_id' => 1,
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => 'missing_cat',
        'label' => null,
        'url' => null,
    ]);

    $root->setRelation('children', collect([$childLink, $childHiddenCat]));

    $items = (new ProjectMenuLayoutItems)([$root], [$parentCat], ['cat_parent' => $parentCat]);

    expect($items)->toHaveCount(1);
    expect($items[0]->children)->toHaveCount(1);
    expect($items[0]->children[0]->id)->toBe($childLink->public_id);
    expect($items[0]->children[0]->label)->toBe('Visible Link');
});

it('projects level-1 category_tree with null reference into storefront root categories and their subcategories asserting exact id list and non-alphabetical tree order', function (): void {
    $subB1 = new CategoryTreeNodeData(
        public_id: 'cat_b1',
        name: 'Sub B1',
        slug: 'sub-b1',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );
    $rootB = new CategoryTreeNodeData(
        public_id: 'cat_b',
        name: 'Root B',
        slug: 'root-b',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [$subB1],
    );

    $subA1 = new CategoryTreeNodeData(
        public_id: 'cat_a1',
        name: 'Sub A1',
        slug: 'sub-a1',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );
    $rootA = new CategoryTreeNodeData(
        public_id: 'cat_a',
        name: 'Root A',
        slug: 'root-a',
        sort_order: 1,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [$subA1],
    );

    $treeItem = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY_TREE,
        'category_public_id' => null,
        'label' => 'Catalog Tree',
        'url' => null,
    ]);

    $storedChild = MenuItem::factory()->make([
        'parent_id' => 1,
        'kind' => MenuItemKind::LINK,
        'label' => 'Stored Child',
        'url' => '/stored',
    ]);
    $treeItem->setRelation('children', collect([$storedChild]));

    $items = (new ProjectMenuLayoutItems)(
        [$treeItem],
        [$rootB, $rootA],
        ['cat_b' => $rootB, 'cat_b1' => $subB1, 'cat_a' => $rootA, 'cat_a1' => $subA1]
    );

    expect($items)->toHaveCount(2);
    expect($items[0]->id)->toBe('cat_b');
    expect($items[0]->label)->toBe('Root B');
    expect($items[0]->icon)->toBeNull();
    expect($items[0]->new_tab)->toBeFalse();
    expect($items[0]->children)->toHaveCount(1);
    expect($items[0]->children[0]->id)->toBe('cat_b1');
    expect($items[0]->children[0]->children)->toBeEmpty();

    expect($items[1]->id)->toBe('cat_a');
    expect($items[1]->label)->toBe('Root A');
    expect($items[1]->icon)->toBeNull();
    expect($items[1]->new_tab)->toBeFalse();
    expect($items[1]->children)->toHaveCount(1);
    expect($items[1]->children[0]->id)->toBe('cat_a1');
    expect($items[1]->children[0]->children)->toBeEmpty();

    expect(collect($items)->pluck('id'))->not->toContain($treeItem->public_id);
    expect(collect($items)->pluck('label'))->not->toContain('Catalog Tree');
    expect(collect($items)->pluck('label'))->not->toContain('Stored Child');
});

it('projects level-1 category_tree with category reference into children of that target category with null icon and false new_tab', function (): void {
    $sub1 = new CategoryTreeNodeData(
        public_id: 'cat_sub_1',
        name: 'Sub 1',
        slug: 'sub-1',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );
    $sub2 = new CategoryTreeNodeData(
        public_id: 'cat_sub_2',
        name: 'Sub 2',
        slug: 'sub-2',
        sort_order: 1,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );
    $parentCat = new CategoryTreeNodeData(
        public_id: 'cat_parent',
        name: 'Parent',
        slug: 'parent',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [$sub1, $sub2],
    );

    $treeItem = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY_TREE,
        'category_public_id' => 'cat_parent',
        'label' => null,
        'url' => null,
    ]);
    $treeItem->setRelation('children', collect());

    $items = (new ProjectMenuLayoutItems)(
        [$treeItem],
        [$parentCat],
        ['cat_parent' => $parentCat, 'cat_sub_1' => $sub1, 'cat_sub_2' => $sub2]
    );

    expect($items)->toHaveCount(2);
    expect($items[0]->id)->toBe('cat_sub_1');
    expect($items[0]->label)->toBe('Sub 1');
    expect($items[0]->icon)->toBeNull();
    expect($items[0]->new_tab)->toBeFalse();

    expect($items[1]->id)->toBe('cat_sub_2');
    expect($items[1]->label)->toBe('Sub 2');
    expect($items[1]->icon)->toBeNull();
    expect($items[1]->new_tab)->toBeFalse();
});

it('omits level-1 category_tree when category reference targets a leaf category', function (): void {
    $leafCat = new CategoryTreeNodeData(
        public_id: 'cat_leaf',
        name: 'Leaf',
        slug: 'leaf',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );

    $treeItem = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY_TREE,
        'category_public_id' => 'cat_leaf',
        'label' => null,
        'url' => null,
    ]);
    $treeItem->setRelation('children', collect());

    $items = (new ProjectMenuLayoutItems)([$treeItem], [$leafCat], ['cat_leaf' => $leafCat]);

    expect($items)->toBeEmpty();
});

it('omits level-1 category_tree when category reference is not in storefront tree', function (): void {
    $treeItem = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY_TREE,
        'category_public_id' => 'missing_cat',
        'label' => null,
        'url' => null,
    ]);
    $treeItem->setRelation('children', collect());

    $items = (new ProjectMenuLayoutItems)([$treeItem], [], []);

    expect($items)->toBeEmpty();
});

it('omits category_tree items positioned below level 1 under a link parent', function (): void {
    $menu = Menu::factory()->create();
    $parent = MenuItem::factory()->forMenu($menu)->create([
        'kind' => MenuItemKind::LINK,
        'label' => 'Parent Link',
        'url' => '/parent',
    ]);
    $childTree = MenuItemFactory::new()->categoryTree()->childOf($parent)->forMenu($menu)->create();
    $parent->setRelation('children', collect([$childTree]));

    $items = (new ProjectMenuLayoutItems)([$parent], [], []);

    expect($items)->toHaveCount(1);
    expect($items[0]->children)->toBeEmpty();
});

it('preserves sibling order with category_tree expansion spliced directly into source slot', function (): void {
    $cat1 = new CategoryTreeNodeData(
        public_id: 'cat_1',
        name: 'Cat 1',
        slug: 'cat-1',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );
    $cat2 = new CategoryTreeNodeData(
        public_id: 'cat_2',
        name: 'Cat 2',
        slug: 'cat-2',
        sort_order: 1,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );

    $link0 = MenuItem::factory()->make([
        'kind' => MenuItemKind::LINK,
        'label' => 'Link 0',
        'url' => '/link-0',
        'sort_order' => 0,
    ]);
    $link0->setRelation('children', collect());

    $tree1 = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY_TREE,
        'category_public_id' => null,
        'label' => null,
        'url' => null,
        'sort_order' => 1,
    ]);
    $tree1->setRelation('children', collect());

    $link2 = MenuItem::factory()->make([
        'kind' => MenuItemKind::LINK,
        'label' => 'Link 2',
        'url' => '/link-2',
        'sort_order' => 2,
    ]);
    $link2->setRelation('children', collect());

    $items = (new ProjectMenuLayoutItems)(
        [$link0, $tree1, $link2],
        [$cat1, $cat2],
        ['cat_1' => $cat1, 'cat_2' => $cat2]
    );

    expect($items)->toHaveCount(4);
    expect($items[0]->label)->toBe('Link 0');
    expect($items[1]->label)->toBe('Cat 1');
    expect($items[2]->label)->toBe('Cat 2');
    expect($items[3]->label)->toBe('Link 2');
});

it('emits the same category twice when reached via both a category item and a category_tree expansion', function (): void {
    $laptopsCat = new CategoryTreeNodeData(
        public_id: 'cat_laptops',
        name: 'Laptops',
        slug: 'laptops',
        sort_order: 0,
        show_in_nav: true,
        is_active: true,
        is_effectively_active: true,
        children: [],
    );

    $item1 = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => 'cat_laptops',
        'label' => null,
        'url' => null,
        'sort_order' => 0,
    ]);
    $item1->setRelation('children', collect());

    $item2 = MenuItem::factory()->make([
        'kind' => MenuItemKind::CATEGORY_TREE,
        'category_public_id' => null,
        'label' => null,
        'url' => null,
        'sort_order' => 1,
    ]);
    $item2->setRelation('children', collect());

    $items = (new ProjectMenuLayoutItems)(
        [$item1, $item2],
        [$laptopsCat],
        ['cat_laptops' => $laptopsCat]
    );

    expect($items)->toHaveCount(2);
    expect($items[0]->id)->toBe($item1->public_id);
    expect($items[0]->label)->toBe('Laptops');
    expect($items[1]->id)->toBe('cat_laptops');
    expect($items[1]->label)->toBe('Laptops');
});

