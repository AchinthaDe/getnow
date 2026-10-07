<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Domain\Content\Policies\BaseContentPolicy;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TestPolicy extends BaseContentPolicy {}

class BaseContentPolicyTest extends TestCase
{
    #[Test]
    public function it_allows_platform_admins(): void
    {
        $user = User::factory()->platformAdmin()->make();
        $policy = new TestPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, null));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, null));
        $this->assertTrue($policy->delete($user, null));
        $this->assertTrue($policy->restore($user, null));
        $this->assertTrue($policy->forceDelete($user, null));
    }

    #[Test]
    public function it_denies_non_admins(): void
    {
        $user = User::factory()->make();
        $policy = new TestPolicy;

        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->view($user, null));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->update($user, null));
        $this->assertFalse($policy->delete($user, null));
        $this->assertFalse($policy->restore($user, null));
        $this->assertFalse($policy->forceDelete($user, null));
    }
}
