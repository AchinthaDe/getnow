<?php

declare(strict_types=1);

namespace App\Domain\Content\Policies;

use App\Models\User;

final class MenuPolicy extends BaseContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_platform_admin;
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->is_platform_admin;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->is_platform_admin;
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
}
