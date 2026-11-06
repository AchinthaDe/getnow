<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Content;

use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;

it('rejects invalid rows at the database', function (string $case, string $sqlState, string $constraint): void {
    $menu = Menu::factory()->create();
    $category = Category::factory()->create();
    try {
        match ($case) {
            'invalid kind' => (function () use ($menu): void {
                $item = MenuItem::factory()->forMenu($menu)->create();
                // raw SQL: the enum cast would throw ValueError before the CHECK runs
                DB::update("UPDATE menu_items SET kind = 'invalid_kind' WHERE id = ?", [$item->id]);
            })(),
            'link without url' => MenuItem::factory()->forMenu($menu)->create(['url' => null]),
            'link with category reference' => MenuItem::factory()->forMenu($menu)->create(['url' => '/test', 'category_public_id' => $category->public_id]),
            'category with url' => MenuItem::factory()->forMenu($menu)->create(['kind' => MenuItemKind::CATEGORY, 'url' => '/test', 'category_public_id' => $category->public_id]),
            'category without reference' => MenuItem::factory()->forMenu($menu)->create(['kind' => MenuItemKind::CATEGORY, 'url' => null, 'category_public_id' => null]),
            'category tree with url' => MenuItem::factory()->forMenu($menu)->create(['kind' => MenuItemKind::CATEGORY_TREE, 'url' => '/test', 'category_public_id' => null]),
            'invalid category foreign key reference' => MenuItem::factory()->forMenu($menu)->create(['kind' => MenuItemKind::CATEGORY, 'url' => null, 'category_public_id' => (string) Str::ulid()]),
            default => throw new \InvalidArgumentException("Unexpected case: {$case}"),
        };
        Assert::fail('Expected a QueryException.');
    } catch (QueryException $e) {
        expect($e->getCode())->toBe($sqlState)->and($e->getMessage())->toContain($constraint);
    }
})->with([
    'invalid kind' => ['invalid kind', '23514', 'menu_items_kind_check'],
    'link without url' => ['link without url', '23514', 'menu_items_kind_shape_check'],
    'link with category reference' => ['link with category reference', '23514', 'menu_items_kind_shape_check'],
    'category with url' => ['category with url', '23514', 'menu_items_kind_shape_check'],
    'category without reference' => ['category without reference', '23514', 'menu_items_kind_shape_check'],
    'category tree with url' => ['category tree with url', '23514', 'menu_items_kind_shape_check'],
    'invalid category foreign key reference' => ['invalid category foreign key reference', '23503', 'menu_items_category_public_id_foreign'],
]);
