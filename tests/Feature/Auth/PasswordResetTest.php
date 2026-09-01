<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_screens_are_not_found(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'test@example.com'])->assertNotFound();
        $this->get('/reset-password/example-token')->assertNotFound();
        $this->post('/reset-password', [
            'token' => 'example-token',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }

    public function test_authenticated_users_cannot_open_password_reset(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/forgot-password')->assertNotFound();
    }
}
