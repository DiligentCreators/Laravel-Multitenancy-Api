<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'type' => $this->faker->randomElement(['info', 'warning', 'update', 'maintenance']),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'is_active' => true,
            'audience_type' => 'all',
            'audience_ids' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Announcement $announcement) {
            // Create related models after the main model is created
        });
    }
}
