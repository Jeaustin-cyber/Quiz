<?php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrupoTest extends TestCase
{
    use RefreshDatabase;

    public function test_grupo_contiene_toda_su_informacion(): void
    {
        $curso = Curso::create([
            'sigla' => fake()->unique()->bothify('IF####'),
            'nombre_curso' => fake()->words(3, true),
            'creditos' => fake()->numberBetween(1, 6),
        ]);

        $docentes = collect(range(1, fake()->numberBetween(1, 2)))->map(
            fn () => Docente::create([
                'cod_empleado' => fake()->unique()->numberBetween(1000, 9999),
                'nombre_empleado' => fake()->firstName(),
                'apellidos_empleado' => fake()->lastName(),
            ])
        );

        $grupo = Grupo::create([
            'numero_ciclo' => fake()->numberBetween(1, 3),
            'ano' => fake()->numberBetween(2020, 2030),
            'numero_grupo' => fake()->numberBetween(1, 10),
            'abierto' => fake()->boolean(),
            'curso_id' => $curso->id,
        ]);

        $grupo->docentes()->attach($docentes->pluck('id'));

        // Mínimo 15 estudiantes, cada uno con su consejero y su nota
        $notas = [];
        $cantidad = fake()->numberBetween(15, 20);

        for ($i = 0; $i < $cantidad; $i++) {
            $estudiante = Estudiante::create([
                'carne' => fake()->unique()->numerify('C#####'),
                'nombre' => fake()->firstName(),
                'apellidos' => fake()->lastName(),
                'docente_id' => $docentes->random()->id,
            ]);

            $notas[$estudiante->id] = fake()->randomFloat(2, 0, 100);
            $grupo->estudiantes()->attach($estudiante->id, ['nota' => $notas[$estudiante->id]]);
        }

        // Los datos están realmente en la base de datos
        $this->assertDatabaseHas('grupos', [
            'id' => $grupo->id,
            'curso_id' => $curso->id,
            'numero_ciclo' => $grupo->numero_ciclo,
            'ano' => $grupo->ano,
            'numero_grupo' => $grupo->numero_grupo,
        ]);
        $this->assertDatabaseCount('docente_grupo', $docentes->count());
        $this->assertDatabaseCount('estudiante_matriculados', $cantidad);

        // Se recarga el grupo desde la base, no se usa el objeto en memoria
        $recargado = Grupo::with(['curso', 'docentes', 'estudiantes.consejero'])->find($grupo->id);

        // Atributos del grupo
        $this->assertSame($grupo->numero_ciclo, $recargado->numero_ciclo);
        $this->assertSame($grupo->ano, $recargado->ano);
        $this->assertSame($grupo->numero_grupo, $recargado->numero_grupo);
        $this->assertSame((bool) $grupo->abierto, $recargado->abierto);

        // Curso: es el mismo registro que se asoció
        $this->assertTrue($recargado->curso->is($curso));

        // Docentes: son exactamente los asociados, entre 1 y 2
        $this->assertGreaterThanOrEqual(1, $recargado->docentes->count());
        $this->assertLessThanOrEqual(2, $recargado->docentes->count());
        $this->assertEqualsCanonicalizing(
            $docentes->pluck('id')->all(),
            $recargado->docentes->pluck('id')->all()
        );

        // Estudiantes: son exactamente los matriculados, al menos 15
        $this->assertGreaterThanOrEqual(15, $recargado->estudiantes->count());
        $this->assertEqualsCanonicalizing(
            array_keys($notas),
            $recargado->estudiantes->pluck('id')->all()
        );

        foreach ($recargado->estudiantes as $estudiante) {
            $this->assertEqualsWithDelta($notas[$estudiante->id], $estudiante->pivot->nota, 0.001);
            $this->assertNotNull($estudiante->consejero);
            $this->assertTrue($docentes->contains('id', $estudiante->consejero->id));
        }
    }
}