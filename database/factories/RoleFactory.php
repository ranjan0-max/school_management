<?php

namespace Database\Factories;

use App\Enums\RoleType;
use App\Models\Role;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => RoleType::School,
            'school_id' => School::factory(),
            'name' => fake()->unique()->jobTitle(),
            'description' => null,
            'all_school_menus' => false,
            'is_active' => true,
        ];
    }

    public function platform(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RoleType::Platform,
            'school_id' => null,
        ]);
    }

    public function allSchoolMenus(): static
    {
        return $this->state(fn (array $attributes) => ['all_school_menus' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
