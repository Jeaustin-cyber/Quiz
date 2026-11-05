<?php

namespace Database\Factories;

use App\Models\Docente;
use App\Models\Estudiante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estudiante>
 */
class EstudianteFactory extends Factory
{
    /**
     * Los carnés generados tienen 6 dígitos para no chocar con C12345.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carne' => fake()->unique()->numerify('C######'),
            'nombre' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'consejero_id' => Docente::factory(),
        ];
    }
}
