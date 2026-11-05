<?php

namespace Database\Factories;

use App\Models\Docente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Docente>
 */
class DocenteFactory extends Factory
{
    /**
     * Los códigos generados empiezan en 10000 para no chocar con 1001, 1002 y 1003.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cod_empleado' => fake()->unique()->numberBetween(10000, 99999),
            'nombre_empleado' => fake()->firstName(),
            'apellidos_empleado' => fake()->lastName(),
        ];
    }
}
