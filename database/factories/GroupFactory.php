<?php

namespace Database\Factories;

use App\Enums\Level;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory()->teacher(),
            'name' => 'TD'.fake()->numberBetween(1, 9),
            'subject' => fake()->randomElement(['Algorithmique', 'Bases de données', 'Réseaux']),
            'level' => fake()->randomElement(Level::cases()),
        ];
    }
}
