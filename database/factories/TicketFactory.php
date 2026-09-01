<?php

namespace Database\Factories;

use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'SF-'.strtoupper(Str::random(5)),
            'public_token' => bin2hex(random_bytes(16)),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
            'product' => 'Harbor Trail Pack',
            'status' => TicketStatus::Submitted,
            'is_seeded' => false,
            'source' => TicketSource::VisitorDemo,
        ];
    }

    public function seeded(): static
    {
        return $this->state(fn (): array => [
            'is_seeded' => true,
            'source' => TicketSource::Seeded,
        ]);
    }
}
