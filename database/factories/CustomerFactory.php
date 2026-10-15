<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $isLead = $this->faker->boolean(50);
        $type = $this->faker->randomElement(Customer::TYPES);

        return [
            'type' => $type,
            'is_lead' => $isLead,
            'lead_status' => $isLead ? $this->faker->randomElement(['new', 'contacted', 'qualified', 'proposal', 'negotiation']) : null,
            'status' => $isLead ? 'active' : $this->faker->randomElement(Customer::CUSTOMER_STATUSES),
            'assigned_to' => User::factory(),
            'created_by' => User::factory(),
            'account_manager_id' => $isLead ? null : User::factory(),
            'source' => $this->faker->randomElement(Customer::SOURCES),
            'estimated_value' => $this->faker->randomFloat(2, 1000, 100000),
            'expected_close_date' => $this->faker->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'description' => $this->faker->sentence(),
            // Individual fields
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'job_title' => $this->faker->jobTitle(),
            // Company fields
            'company_name' => $type === 'company' ? $this->faker->company() : null,
            'industry' => $type === 'company' ? $this->faker->randomElement(['technology', 'healthcare', 'finance', 'retail']) : null,
            'website' => $type === 'company' ? $this->faker->url() : null,
            'tax_id' => $type === 'company' ? $this->faker->bothify('TAX-########') : null,
            // Legacy snapshot columns
            'contact_name' => $this->faker->name(),
            'contact_email' => $this->faker->safeEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'contract_value' => $isLead ? null : $this->faker->randomFloat(2, 5000, 500000),
            'health_score' => $this->faker->numberBetween(60, 100),
            'last_contacted_at' => now(),
            'next_follow_up_at' => now()->addDays(7),
            'notes' => $this->faker->sentence(),
            'converted_at' => $isLead ? null : now(),
            'contacts' => [
                [
                    'first_name' => $this->faker->firstName(),
                    'last_name' => $this->faker->lastName(),
                    'email' => $this->faker->safeEmail(),
                    'phone' => $this->faker->phoneNumber(),
                    'title' => $this->faker->jobTitle(),
                    'is_primary' => true,
                ],
            ],
            'addresses' => [
                [
                    'label' => 'HQ',
                    'type' => 'office',
                    'line_1' => $this->faker->streetAddress(),
                    'city' => $this->faker->city(),
                    'state' => $this->faker->state(),
                    'postal_code' => $this->faker->postcode(),
                    'country' => $this->faker->country(),
                    'is_primary' => true,
                ],
            ],
        ];
    }

    public function company(): static
    {
        return $this->state(['type' => 'company', 'company_name' => fake()->company()]);
    }

    public function individual(): static
    {
        return $this->state(['type' => 'individual', 'company_name' => null]);
    }

    public function lead(): static
    {
        return $this->state(['is_lead' => true, 'lead_status' => 'new', 'converted_at' => null]);
    }

    public function customer(): static
    {
        return $this->state(['is_lead' => false, 'lead_status' => null, 'status' => 'active', 'converted_at' => now()]);
    }

    public function atRisk(): static
    {
        return $this->state(['health_score' => fake()->numberBetween(0, 39)]);
    }
}
