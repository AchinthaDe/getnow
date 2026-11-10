<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\ProductModel\ActivateProductModel;
use App\Domain\Catalog\Actions\ProductModel\DeactivateProductModel;
use App\Domain\Catalog\Models\ProductModel;
use Database\Factories\ProductModelFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\assertDatabaseHas;

it('activates an inactive product model, stamps updater, and logs product_model.activated', function (): void {
    $user = UserFactory::new()->create();
    $model = ProductModelFactory::new()->inactive()->create();

    $action = new ActivateProductModel;
    $action($model->public_id, $user);

    $fresh = $model->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->is_active)->toBeTrue()
        ->and($fresh->updated_by)->toBe($user->id);

    assertDatabaseHas('product_models', [
        'id' => $model->id,
        'is_active' => true,
        'updated_by' => $user->id,
    ]);

    $activity = Activity::where('log_name', 'catalog')
        ->where('event', 'product_model.activated')
        ->where('subject_type', ProductModel::class)
        ->where('subject_id', $model->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('product_model.activated')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->toArray())->toBe([
            'public_id' => $model->public_id,
            'old_is_active' => false,
            'new_is_active' => true,
        ]);
});

it('deactivates an active product model, stamps updater, and logs product_model.deactivated', function (): void {
    $user = UserFactory::new()->create();
    $model = ProductModelFactory::new()->create();

    $action = new DeactivateProductModel;
    $action($model->public_id, $user);

    $fresh = $model->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->is_active)->toBeFalse()
        ->and($fresh->updated_by)->toBe($user->id);

    assertDatabaseHas('product_models', [
        'id' => $model->id,
        'is_active' => false,
        'updated_by' => $user->id,
    ]);

    $activity = Activity::where('log_name', 'catalog')
        ->where('event', 'product_model.deactivated')
        ->where('subject_type', ProductModel::class)
        ->where('subject_id', $model->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('product_model.deactivated')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->toArray())->toBe([
            'public_id' => $model->public_id,
            'old_is_active' => true,
            'new_is_active' => false,
        ]);
});

it('treats activation of already active model as idempotent no-op', function (): void {
    $activeModel = ProductModelFactory::new()->create();

    DB::enableQueryLog();

    $activateAction = new ActivateProductModel;
    $activateAction($activeModel->public_id);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $updateQueries = array_filter($queries, fn (array $q) => str_starts_with(strtoupper((string) $q['query']), 'UPDATE'));

    expect($updateQueries)->toBeEmpty();
    expect(Activity::where('subject_type', ProductModel::class)->count())->toBe(0);
});

it('treats deactivation of already inactive model as idempotent no-op', function (): void {
    $inactiveModel = ProductModelFactory::new()->inactive()->create();

    DB::enableQueryLog();

    $deactivateAction = new DeactivateProductModel;
    $deactivateAction($inactiveModel->public_id);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $updateQueries = array_filter($queries, fn (array $q) => str_starts_with(strtoupper((string) $q['query']), 'UPDATE'));

    expect($updateQueries)->toBeEmpty();
    expect(Activity::where('subject_type', ProductModel::class)->count())->toBe(0);
});

it('throws model not found exception for non-existent public ID', function (): void {
    $randomUlid = (string) Str::ulid();

    $action = new ActivateProductModel;
    expect(fn () => $action($randomUlid))->toThrow(ModelNotFoundException::class);
});
