<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'name'       => $this->faker->sentence(3),
            'subject'    => $this->faker->sentence(),
            'body'       => '<p>Hello {{first_name}},</p><p>' . $this->faker->paragraph() . '</p>',
            'created_by' => User::factory(),
        ];
    }
}
