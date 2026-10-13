<?php

declare(strict_types=1);

namespace App\Domain\Content\Policies;

use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;

final class ContentBlockPolicy extends BaseContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, mixed $model): bool
    {
        if ($model instanceof ContentBlock && $model->status === PublishStatus::ARCHIVED) {
            return false;
        }

        return $user->isAdmin();
    }

    public function delete(User $user, mixed $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, mixed $model): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $user->isAdmin();
    }

    public function replicate(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function publish(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function archive(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function enable(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }

    public function disable(User $user, mixed $model): bool
    {
        return $user->isAdmin();
    }
}
