<?php

declare(strict_types=1);

use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Policies\ContentBlockPolicy;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

it('is registered for ContentBlock', function () {
    expect(Gate::getPolicyFor(ContentBlock::class))->toBeInstanceOf(ContentBlockPolicy::class);
});

it('allows admin to view, create, update, reorder and replicate', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    $block = ContentBlock::factory()->create();
    $policy = new ContentBlockPolicy;

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $block))->toBeTrue()
        ->and($policy->create($admin))->toBeTrue()
        ->and($policy->update($admin, $block))->toBeTrue()
        ->and($policy->reorder($admin))->toBeTrue()
        ->and($policy->replicate($admin, $block))->toBeTrue();
});

it('denies non-admin everywhere', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $block = ContentBlock::factory()->create();
    $policy = new ContentBlockPolicy;

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $block))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $block))->toBeFalse()
        ->and($policy->reorder($user))->toBeFalse()
        ->and($policy->replicate($user, $block))->toBeFalse()
        ->and($policy->delete($user, $block))->toBeFalse()
        ->and($policy->deleteAny($user))->toBeFalse()
        ->and($policy->restore($user, $block))->toBeFalse()
        ->and($policy->restoreAny($user))->toBeFalse()
        ->and($policy->forceDelete($user, $block))->toBeFalse()
        ->and($policy->forceDeleteAny($user))->toBeFalse();

    Auth::login($user);
    expect(ContentBlockResource::canViewAny())->toBeFalse()
        ->and(ContentBlockResource::canCreate())->toBeFalse();
});

it('denies delete, restore, and force delete even for admins', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    $block = ContentBlock::factory()->create();
    $policy = new ContentBlockPolicy;

    expect($policy->delete($admin, $block))->toBeFalse()
        ->and($policy->deleteAny($admin))->toBeFalse()
        ->and($policy->restore($admin, $block))->toBeFalse()
        ->and($policy->restoreAny($admin))->toBeFalse()
        ->and($policy->forceDelete($admin, $block))->toBeFalse()
        ->and($policy->forceDeleteAny($admin))->toBeFalse();
});
