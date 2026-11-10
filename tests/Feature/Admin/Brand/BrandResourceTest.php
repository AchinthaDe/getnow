<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Brand;
use App\Filament\Admin\Resources\BrandResource;
use App\Filament\Admin\Resources\BrandResource\Pages\CreateBrand;
use App\Filament\Admin\Resources\BrandResource\Pages\EditBrand;
use App\Filament\Admin\Resources\BrandResource\Pages\ListBrands;
use Database\Factories\BrandFactory;
use Database\Factories\UserFactory;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\AssertsQueryCounts;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('renders list, create, and edit pages for platform admin via smoke test', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    $brand = BrandFactory::new()->create();

    actingAs($admin);

    get(ListBrands::getUrl())->assertSuccessful();
    Livewire::test(ListBrands::class)->assertSuccessful();

    get(BrandResource::getUrl('create'))->assertSuccessful();
    Livewire::test(CreateBrand::class)->assertSuccessful();

    get(BrandResource::getUrl('edit', ['record' => $brand]))->assertSuccessful();
    Livewire::test(EditBrand::class, ['record' => $brand->public_id])->assertSuccessful();
});

it('authorizes platform admin to access brand resource and forbids non-admin users', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    $nonAdmin = UserFactory::new()->create(['is_platform_admin' => false]);
    $brand = BrandFactory::new()->create();

    // 1. Direct policy assertions
    expect($admin->can('viewAny', Brand::class))->toBeTrue()
        ->and($admin->can('view', $brand))->toBeTrue()
        ->and($admin->can('create', Brand::class))->toBeTrue()
        ->and($admin->can('update', $brand))->toBeTrue()
        ->and($admin->can('delete', $brand))->toBeFalse()
        ->and($admin->can('deleteAny', Brand::class))->toBeFalse()
        ->and($nonAdmin->can('viewAny', Brand::class))->toBeFalse()
        ->and($nonAdmin->can('view', $brand))->toBeFalse()
        ->and($nonAdmin->can('create', Brand::class))->toBeFalse()
        ->and($nonAdmin->can('update', $brand))->toBeFalse()
        ->and($nonAdmin->can('delete', $brand))->toBeFalse()
        ->and($nonAdmin->can('deleteAny', Brand::class))->toBeFalse();

    // 2. Route assertions as platform admin
    actingAs($admin);
    get('/admin/brands')->assertSuccessful();
    get('/admin/brands/create')->assertSuccessful();
    get('/admin/brands/'.$brand->public_id.'/edit')->assertSuccessful();

    // 3. Route assertions as non-admin user
    actingAs($nonAdmin);
    get('/admin/brands')->assertForbidden();
    get('/admin/brands/create')->assertForbidden();
    get('/admin/brands/'.$brand->public_id.'/edit')->assertForbidden();

    // 4. Guest access redirects
    auth()->logout();
    get('/admin/brands')->assertRedirect('/login');
});

it('renders brands with name, slug, status, in-nav, and constant query count', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $brands = BrandFactory::new()->count(5)->create();

    AssertsQueryCounts::assertLessThanOrEqual(5, function () use ($brands): void {
        Livewire::test(ListBrands::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($brands);
    });

    // Verify query count does not scale with row count (constant / bounded)
    BrandFactory::new()->count(15)->create();

    AssertsQueryCounts::assertLessThanOrEqual(5, function (): void {
        Livewire::test(ListBrands::class)->assertSuccessful();
    });
});

it('searches brands case-insensitively by name and slug', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $google = BrandFactory::new()->create(['name' => 'Google', 'slug' => 'google']);
    $apple = BrandFactory::new()->create(['name' => 'Apple', 'slug' => 'apple']);
    $samsung = BrandFactory::new()->create(['name' => 'Samsung', 'slug' => 'samsung']);

    // Case-insensitive search by name
    Livewire::test(ListBrands::class)
        ->searchTable('gOOg')
        ->assertCanSeeTableRecords([$google])
        ->assertCanNotSeeTableRecords([$apple, $samsung]);

    // Search by slug
    Livewire::test(ListBrands::class)
        ->searchTable('appl')
        ->assertCanSeeTableRecords([$apple])
        ->assertCanNotSeeTableRecords([$google, $samsung]);
});

it('filters brands by is_active and show_in_nav', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $activeInNav = BrandFactory::new()->create(['name' => 'Brand A', 'is_active' => true, 'show_in_nav' => true]);
    $activeNotInNav = BrandFactory::new()->create(['name' => 'Brand B', 'is_active' => true, 'show_in_nav' => false]);
    $inactiveNotInNav = BrandFactory::new()->inactive()->create(['name' => 'Brand C', 'show_in_nav' => false]);

    // Filter by is_active = true
    Livewire::test(ListBrands::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$activeInNav, $activeNotInNav])
        ->assertCanNotSeeTableRecords([$inactiveNotInNav]);

    // Filter by is_active = false
    Livewire::test(ListBrands::class)
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$inactiveNotInNav])
        ->assertCanNotSeeTableRecords([$activeInNav, $activeNotInNav]);

    // Filter by show_in_nav = true
    Livewire::test(ListBrands::class)
        ->filterTable('show_in_nav', true)
        ->assertCanSeeTableRecords([$activeInNav])
        ->assertCanNotSeeTableRecords([$activeNotInNav, $inactiveNotInNav]);

    // Filter by show_in_nav = false
    Livewire::test(ListBrands::class)
        ->filterTable('show_in_nav', false)
        ->assertCanSeeTableRecords([$activeNotInNav, $inactiveNotInNav])
        ->assertCanNotSeeTableRecords([$activeInNav]);
});

it('creates a brand via CreateBrand action and surfaces duplicate error cleanly', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    BrandFactory::new()->create(['name' => 'Samsung', 'slug' => 'samsung']);

    // 1. Happy path: submit "  Sony  " with surrounding whitespace and show_in_nav = true
    Livewire::test(CreateBrand::class)
        ->fillForm([
            'name' => '  Sony  ',
            'show_in_nav' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var Brand $sony */
    $sony = Brand::where('name', 'Sony')->firstOrFail();
    expect($sony->name)->toBe('Sony')
        ->and($sony->slug)->toBe('sony')
        ->and($sony->is_active)->toBeTrue()
        ->and($sony->show_in_nav)->toBeTrue()
        ->and($sony->created_by)->toBe($admin->id)
        ->and($sony->updated_by)->toBe($admin->id);

    // Verify activity log
    $activity = Activity::where('subject_type', Brand::class)
        ->where('subject_id', $sony->id)
        ->where('event', 'brand.created')
        ->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->log_name)->toBe('catalog');

    // 2. Failure path: case-insensitive duplicate (" samsung ")
    Livewire::test(CreateBrand::class)
        ->fillForm([
            'name' => ' samsung ',
            'show_in_nav' => false,
        ])
        ->call('create')
        ->assertNotified('Cannot Create Brand');

    expect(Brand::whereRaw('lower(name) = ?', ['samsung'])->count())->toBe(1);

    // 3. Failure path: invalid/empty-slug name ("???")
    Livewire::test(CreateBrand::class)
        ->fillForm([
            'name' => '???',
            'show_in_nav' => false,
        ])
        ->call('create')
        ->assertNotified('Cannot Create Brand');

    expect(Brand::where('name', '???')->exists())->toBeFalse();
});

it('edits brand name and show_in_nav while preserving slug and surfaces duplicate error cleanly', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $sony = BrandFactory::new()->create([
        'name' => 'Sony',
        'slug' => 'sony',
        'show_in_nav' => true,
    ]);
    BrandFactory::new()->create([
        'name' => 'Panasonic',
        'slug' => 'panasonic',
    ]);

    // 1. Happy path: edit name and show_in_nav (true -> false), slug preserved
    Livewire::test(EditBrand::class, ['record' => $sony->public_id])
        ->fillForm([
            'name' => 'Sony Mobile',
            'show_in_nav' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $sony->refresh();
    expect($sony->name)->toBe('Sony Mobile')
        ->and($sony->show_in_nav)->toBeFalse()
        ->and($sony->slug)->toBe('sony')
        ->and($sony->updated_by)->toBe($admin->id);

    // Verify activity log
    $activity = Activity::where('subject_type', Brand::class)
        ->where('subject_id', $sony->id)
        ->where('event', 'brand.updated')
        ->latest('id')
        ->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->log_name)->toBe('catalog');

    // 2. Happy path: self-rename case change with show_in_nav flipped back to true (Guard 1)
    Livewire::test(EditBrand::class, ['record' => $sony->public_id])
        ->fillForm([
            'name' => 'SONY MOBILE',
            'show_in_nav' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $sony->refresh();
    expect($sony->name)->toBe('SONY MOBILE')
        ->and($sony->show_in_nav)->toBeTrue()
        ->and($sony->slug)->toBe('sony');

    // 3. Failure path: collision with another brand ("panasonic")
    Livewire::test(EditBrand::class, ['record' => $sony->public_id])
        ->fillForm([
            'name' => 'panasonic',
        ])
        ->call('save')
        ->assertNotified('Cannot Update Brand');

    expect($sony->fresh()->name)->toBe('SONY MOBILE');
});

it('activates and deactivates brands via dedicated table and header actions', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $nokia = BrandFactory::new()->create(['name' => 'Nokia', 'is_active' => true]);
    $motorola = BrandFactory::new()->inactive()->create(['name' => 'Motorola', 'is_active' => false]);

    // 1. Deactivate active brand via table action
    Livewire::test(ListBrands::class)
        ->callTableAction('deactivate', record: $nokia)
        ->assertNotified('Brand Deactivated');

    expect($nokia->fresh()->is_active)->toBeFalse();

    $deactActivity = Activity::where('subject_type', Brand::class)
        ->where('subject_id', $nokia->id)
        ->where('event', 'brand.deactivated')
        ->latest('id')
        ->first();
    expect($deactActivity)->not->toBeNull()
        ->and($deactActivity->causer_id)->toBe($admin->id);

    // 2. Activate inactive brand via table action
    Livewire::test(ListBrands::class)
        ->callTableAction('activate', record: $motorola)
        ->assertNotified('Brand Activated');

    expect($motorola->fresh()->is_active)->toBeTrue();

    $actActivity = Activity::where('subject_type', Brand::class)
        ->where('subject_id', $motorola->id)
        ->where('event', 'brand.activated')
        ->latest('id')
        ->first();
    expect($actActivity)->not->toBeNull()
        ->and($actActivity->causer_id)->toBe($admin->id);

    // 3. Reactivate Nokia via header action on EditBrand page
    Livewire::test(EditBrand::class, ['record' => $nokia->public_id])
        ->callAction('activate')
        ->assertNotified('Brand Activated');

    expect($nokia->fresh()->is_active)->toBeTrue();
});
