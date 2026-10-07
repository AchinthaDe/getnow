<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_admins_cannot_access_the_admin_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    #[Test]
    public function admins_can_access_the_admin_panel(): void
    {
        $user = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertSuccessful();
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/login');
    }
}
