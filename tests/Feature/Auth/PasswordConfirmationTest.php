<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_is_not_found(): void
    {
        $this->get('/user/confirm-password')->assertNotFound();
        $this->post('/user/confirm-password', ['password' => 'password'])->assertNotFound();
    }

    public function test_authenticated_users_cannot_open_confirm_password(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/user/confirm-password')->assertNotFound();
    }
}
