<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Gate;

test('one-click demo agent login authenticates without a login form', function () {
    $this->seed(UserSeeder::class);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Open Agent Dashboard')
        ->assertDontSee('name="password"', false)
        ->assertDontSee('Log in to your account');

    $this->post(route('demo.enter-agent'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    expect(auth()->user()?->role)->toBe(UserRole::DemoAgent);
    expect(auth()->user()?->email)->toBe(config('supportflow.brand.agent_email'));
});

test('guests cannot open the agent dashboard or queue', function () {
    $this->get(route('dashboard'))->assertRedirect(route('home'));
    $this->get(route('agent.tickets.index'))->assertRedirect(route('home'));
});

test('credential screens are not found', function () {
    $this->get('/login')->assertNotFound();
    $this->post('/login')->assertNotFound();
    $this->get('/register')->assertNotFound();
});

test('demo agent cannot view secrets, raw prompts, user management, or force reset', function () {
    $agent = User::factory()->create(['role' => UserRole::DemoAgent]);

    $this->actingAs($agent);

    expect(Gate::forUser($agent)->allows('view-secrets'))->toBeFalse();
    expect(Gate::forUser($agent)->allows('view-raw-prompts'))->toBeFalse();
    expect(Gate::forUser($agent)->allows('manage-users'))->toBeFalse();
    expect(Gate::forUser($agent)->allows('force-reset-demo'))->toBeFalse();
    expect(Gate::forUser($agent)->allows('prune-stale-demo'))->toBeTrue();

    $this->get('/telescope')->assertNotFound();
    expect(array_key_exists('demo.reset.force', app('router')->getRoutes()->getRoutesByName()))->toBeFalse();
});

test('admin can force reset via policy', function () {
    $admin = User::factory()->admin()->create();

    expect(Gate::forUser($admin)->allows('force-reset-demo'))->toBeTrue();
});

test('demo agent login is throttled', function () {
    $this->seed(UserSeeder::class);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.88']);

    for ($i = 0; $i < 10; $i++) {
        $this->post(route('demo.enter-agent'));
    }

    $this->post(route('demo.enter-agent'))->assertStatus(429);
});

test('registration is disabled', function () {
    $this->get('/register')->assertNotFound();
});
