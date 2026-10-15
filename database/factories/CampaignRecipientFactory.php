<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignRecipient>
 */
class CampaignRecipientFactory extends Factory
{
    protected $model = CampaignRecipient::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'customer_id' => Customer::factory(),
            'email' => fake()->safeEmail(),
            'status' => fake()->randomElement(['pending', 'sent', 'sent', 'failed']),
            'sent_at' => now(),
        ];
    }
}
