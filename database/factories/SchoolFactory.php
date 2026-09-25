<?php

namespace Database\Factories;

use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' School';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'code' => strtoupper(fake()->unique()->bothify('SCH-####')),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('##########'),
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'currency' => 'INR',
            'status' => SchoolStatus::Trial,
            'trial_ends_at' => now()->addDays(30),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SchoolStatus::Active,
            'trial_ends_at' => null,
        ]);
    }
}
