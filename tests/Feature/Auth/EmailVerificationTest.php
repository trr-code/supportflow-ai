<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screens_are_not_found(): void
    {
        $this->get('/email/verify')->assertNotFound();

        $this->actingAs(User::factory()->unverified()->create());

        $this->get('/email/verify')->assertNotFound();
        $this->post('/email/verification-notification')->assertNotFound();
    }
}
