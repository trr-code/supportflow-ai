<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_settings_are_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/settings/security')
            ->assertNotFound();

        $this->actingAs($user)
            ->post('/settings/security')
            ->assertNotFound();
    }

    public function test_passkey_and_two_factor_routes_are_not_found(): void
    {
        $this->get('/.well-known/passkey-endpoints')->assertNotFound();
        $this->get('/passkeys/login')->assertNotFound();
        $this->post('/passkeys/login')->assertNotFound();
        $this->post('/passkeys/login/options')->assertNotFound();
        $this->get('/user/two-factor-authentication')->assertNotFound();
        $this->post('/user/two-factor-authentication')->assertNotFound();
        $this->get('/user/two-factor-recovery-codes')->assertNotFound();
    }

    public function test_appearance_settings_are_not_found(): void
    {
        $user = User::factory()->create();

        $this->get('/settings/appearance')->assertNotFound();

        $this->actingAs($user)
            ->get('/settings/appearance')
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Appearance');

        $this->assertFalse(array_key_exists('appearance.edit', app('router')->getRoutes()->getRoutesByName()));
    }
}
