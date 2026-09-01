<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_challenge_is_not_found(): void
    {
        $this->get('/two-factor-challenge')->assertNotFound();
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertNotFound();
    }

    public function test_authenticated_users_cannot_open_two_factor_challenge(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/two-factor-challenge')->assertNotFound();
    }
}
