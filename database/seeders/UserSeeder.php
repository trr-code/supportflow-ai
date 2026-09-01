<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => config('supportflow.brand.agent_email')],
            [
                'name' => config('supportflow.brand.agent_name'),
                'password' => Hash::make(Str::password(32)),
                'email_verified_at' => now(),
                'role' => UserRole::DemoAgent,
            ],
        );
    }
}
