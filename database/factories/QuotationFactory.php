<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'customer_id' => Customer::factory(),
            'created_by' => User::factory(),
            'quotation_number' => 'QT-'.fake()->unique()->numerify('####'),
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal' => 100,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 100,
            'valid_until' => now()->addDays(30),
        ];
    }
}
