<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grupo>
 */
class GrupoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_ciclo' => fake()->numberBetween(1, 3),
            'ano' => 2026,
            'numero_grupo' => fake()->numberBetween(1, 9),
            'abierto' => false,
            'curso_id' => Curso::factory(),
        ];
    }

    public function abierto(): static
    {
        return $this->state(fn (): array => ['abierto' => true]);
    }
}
