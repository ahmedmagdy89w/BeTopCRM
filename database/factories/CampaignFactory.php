<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'name'              => $this->faker->sentence(3),
            'email_template_id' => EmailTemplate::factory(),
            'status'            => 'draft',
            'segment_filters'   => [],
            'created_by'        => User::factory(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state([
            'status'       => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);
    }
}
