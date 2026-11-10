<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Brand;
use App\Models\User;

final class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_platform_admin;
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->is_platform_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_platform_admin;
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->is_platform_admin;
    }

    public function delete(User $user, Brand $brand): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
