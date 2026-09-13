<?php

namespace Database\Factories;

use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->buildingNumber(),
            'sort_order' => fake()->numberBetween(1, 100),
            'active' => true,
        ];
    }
}
