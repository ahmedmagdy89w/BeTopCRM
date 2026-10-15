<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'url'         => 'https://example.com/webhook/' . Str::random(8),
            'secret'      => Str::random(40),
            'events'      => ['lead.created', 'lead.updated'],
            'is_active'   => true,
            'description' => $this->faker->sentence(),
            'created_by'  => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
