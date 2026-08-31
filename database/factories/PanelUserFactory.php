<?php

namespace Database\Factories;

use App\Models\PanelUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PanelUser>
 */
class PanelUserFactory extends Factory
{
    protected $model = PanelUser::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'is_active' => true,
        ];
    }
}
