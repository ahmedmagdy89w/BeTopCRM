<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 5000, 500000);

        return [
            'opportunity_id' => Opportunity::factory(),
            'customer_id' => Customer::factory(),
            'created_by' => User::factory(),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'status' => 'issued',
            'currency' => 'EGP',
            'subtotal' => $subtotal,
            'tax_percent' => 14,
            'tax_amount' => round($subtotal * 0.14, 2),
            'discount_amount' => 0,
            'total' => round($subtotal * 1.14, 2),
            'issued_at' => now()->toDateString(),
        ];
    }
}
