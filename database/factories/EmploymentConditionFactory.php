<?php

namespace Database\Factories;

use App\Models\Collaborator;
use App\Models\EmploymentCondition;
use App\Models\JobRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmploymentCondition> */
class EmploymentConditionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'collaborator_id' => Collaborator::factory(),
            'job_role_id' => JobRole::factory(),
            'monthly_salary' => fake()->randomFloat(2, 1000, 10000),
            'weekly_hours' => fake()->randomFloat(2, 1, 80),
            'effective_from' => today(),
            'effective_to' => null,
            'reason' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
