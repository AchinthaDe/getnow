<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;

it('enables all strict model checks in non-production environments', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

it('throws LazyLoadingViolationException when accessing an unloaded relationship', function (): void {
    $menus = Menu::factory()->count(2)->create();
    MenuItem::factory()->forMenu($menus->first())->create();

    $loaded = Menu::all();

    expect(fn () => $loaded->first()->items)
        ->toThrow(LazyLoadingViolationException::class);
});

it('throws MassAssignmentException when mass-assigning unfillable attributes', function (): void {
    $user = User::factory()->create();

    expect(fn () => $user->fill(['non_fillable_custom_attribute' => 'forbidden_value']))
        ->toThrow(MassAssignmentException::class);
});

it('throws MissingAttributeException when reading an attribute omitted from select', function (): void {
    $user = User::factory()->create();

    $selectedUser = User::query()->select(['id'])->where('id', $user->id)->firstOrFail();

    expect(fn () => $selectedUser->email)
        ->toThrow(MissingAttributeException::class);
});

it('passes query count assertion when exact query count matches and forwards return value', function (): void {
    $result = assertQueryCount(2, function (): string {
        DB::select('SELECT 1');
        DB::select('SELECT 1');

        return 'result_payload';
    });

    expect($result)->toBe('result_payload');
});

it('fails query count assertion when exact query count does not match', function (): void {
    $caught = false;

    try {
        assertQueryCount(1, function (): void {
            DB::select('SELECT 1');
            DB::select('SELECT 1');
        });
    } catch (AssertionFailedError $e) {
        $caught = true;
        expect($e->getMessage())->toContain('Expected exactly 1 database queries, but 2 were executed')
            ->and($e->getMessage())->toContain('SELECT 1');
    }

    expect($caught)->toBeTrue();
});

it('passes max query count assertion when count is less than or equal to threshold', function (): void {
    $resultUnder = assertQueryCountLessThanOrEqual(3, function (): int {
        DB::select('SELECT 1');
        DB::select('SELECT 1');

        return 42;
    });

    $resultEqual = assertQueryCountLessThanOrEqual(2, function (): int {
        DB::select('SELECT 1');
        DB::select('SELECT 1');

        return 42;
    });

    expect($resultUnder)->toBe(42)
        ->and($resultEqual)->toBe(42);
});

it('fails max query count assertion when count exceeds threshold', function (): void {
    $caught = false;

    try {
        assertQueryCountLessThanOrEqual(2, function (): void {
            DB::select('SELECT 1');
            DB::select('SELECT 1');
            DB::select('SELECT 1');
        });
    } catch (AssertionFailedError $e) {
        $caught = true;
        expect($e->getMessage())->toContain('Expected at most 2 database queries, but 3 were executed');
    }

    expect($caught)->toBeTrue();
});

it('cleans up and disables query log in finally block even when callback throws an exception', function (): void {
    $caught = false;

    try {
        assertQueryCount(1, function (): void {
            DB::select('SELECT 1');
            throw new RuntimeException('Callback failed');
        });
    } catch (RuntimeException $e) {
        $caught = true;
        expect($e->getMessage())->toBe('Callback failed');
    }

    expect($caught)->toBeTrue()
        ->and(DB::logging())->toBeFalse()
        ->and(DB::getQueryLog())->toBe([]);
});

it('cleans up and disables query log in finally block even when count assertion fails', function (): void {
    $caught = false;

    try {
        assertQueryCount(1, function (): void {
            DB::select('SELECT 1');
            DB::select('SELECT 1');
        });
    } catch (AssertionFailedError) {
        $caught = true;
    }

    expect($caught)->toBeTrue()
        ->and(DB::logging())->toBeFalse()
        ->and(DB::getQueryLog())->toBe([]);
});
