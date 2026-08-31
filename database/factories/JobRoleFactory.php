<?php

namespace Database\Factories;

use App\Models\JobRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobRole> */
class JobRoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'description' => fake()->optional()->sentence(),
            'reference_weekly_hours' => 48,
            'reference_monthly_salary' => 3000,
            'is_active' => true,
        ];
    }
}
