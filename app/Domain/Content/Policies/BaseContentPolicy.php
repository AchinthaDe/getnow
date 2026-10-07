<?php

declare(strict_types=1);

namespace App\Domain\Content\Policies;

use App\Models\User;

abstract class BaseContentPolicy
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
        return $user->is_platform_admin;
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->is_platform_admin;
    }

    public function delete(User $user, mixed $model): bool
    {
        return $user->is_platform_admin;
    }

    public function restore(User $user, mixed $model): bool
    {
        return $user->is_platform_admin;
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return $user->is_platform_admin;
    }
}
