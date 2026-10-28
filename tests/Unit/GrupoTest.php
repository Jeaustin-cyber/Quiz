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
            'sigla' => 'IF6100',
            'nombre_curso' => 'Análisis y Diseño de Sistemas',
            'creditos' => 4,
        ]);

        $docente1 = Docente::create([
            'cod_empleado' => 1001,
            'nombre_empleado' => 'Ana',
            'apellidos_empleado' => 'Mora Rojas',
        ]);
        $docente2 = Docente::create([
            'cod_empleado' => 1002,
            'nombre_empleado' => 'Luis',
            'apellidos_empleado' => 'Vargas Solano',
        ]);

        $grupo = Grupo::create([
            'numero_ciclo' => 2,
            'ano' => 2026,
            'numero_grupo' => 1,
            'abierto' => true,
            'curso_id' => $curso->id,
        ]);

        $grupo->docentes()->attach([$docente1->id, $docente2->id]);

        for ($i = 1; $i <= 15; $i++) {
            $estudiante = Estudiante::create([
                'carne' => 'C' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'nombre' => "Estudiante$i",
                'apellidos' => "Apellido$i",
                'docente_id' => $docente1->id,
            ]);

            $grupo->estudiantes()->attach($estudiante->id, ['nota' => 80.5]);
        }

        $grupo = Grupo::with(['curso', 'docentes', 'estudiantes.consejero'])
            ->find($grupo->id);

        $this->assertSame(2, $grupo->numero_ciclo);
        $this->assertSame(2026, $grupo->ano);
        $this->assertSame(1, $grupo->numero_grupo);
        $this->assertTrue($grupo->abierto);

        $this->assertSame('IF6100', $grupo->curso->sigla);
        $this->assertSame('Análisis y Diseño de Sistemas', $grupo->curso->nombre_curso);
        $this->assertSame(4, $grupo->curso->creditos);

        $this->assertCount(2, $grupo->docentes);
        $this->assertGreaterThanOrEqual(1, $grupo->docentes->count());
        $this->assertLessThanOrEqual(2, $grupo->docentes->count());
        $this->assertTrue($grupo->docentes->contains('cod_empleado', 1001));
        $this->assertTrue($grupo->docentes->contains('cod_empleado', 1002));

        $this->assertCount(15, $grupo->estudiantes);
        $this->assertGreaterThanOrEqual(15, $grupo->estudiantes->count());

        foreach ($grupo->estudiantes as $estudiante) {
            $this->assertEquals(80.5, $estudiante->pivot->nota);
            $this->assertSame('Ana', $estudiante->consejero->nombre_empleado);
        }
    }
}