<?php

namespace Database\Factories;

use App\Models\Collaborator;
use App\Models\JobRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Collaborator> */
class CollaboratorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_role_id' => JobRole::factory(),
            'full_name' => fake()->name(),
            'identity_document' => fake()->unique()->numerify('########'),
            'biometric_id' => fake()->unique()->numberBetween(1, 999999),
            'occupation_status' => fake()->optional()->randomElement([
                Collaborator::OCCUPATION_STUDENT,
                Collaborator::OCCUPATION_FULL_TIME,
                Collaborator::OCCUPATION_PART_TIME,
            ]),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'hire_date' => fake()->dateTimeBetween('-5 years', 'today'),
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
