<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'booking-form.pdf', 'unit-brochure.pdf', 'payment-plan.pdf',
            'id-copy.jpg', 'contract-draft.docx', 'site-plan.png',
        ]);

        return [
            'uploaded_by' => User::factory(),
            'original_name' => $name,
            'stored_name' => fake()->uuid().'-'.$name,
            'mime_type' => fake()->randomElement(Attachment::ALLOWED_MIMES),
            'size' => fake()->numberBetween(50_000, 5_000_000),
            'disk' => 'local',
            'path' => 'attachments/'.fake()->uuid().'/'.$name,
        ];
    }
}
