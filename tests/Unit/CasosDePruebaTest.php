<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function cursoIF6100(): Curso
{
    return Curso::create([
        'sigla' => 'IF6100',
        'nombre_curso' => 'Análisis y Diseño de Sistemas',
        'creditos' => 4,
    ]);
}

function docenteConCodigo(int $codigo): Docente
{
    $datos = [
        1001 => ['Ana', 'Mora Vargas'],
        1002 => ['Luis', 'Rojas Solano'],
        1003 => ['Carla', 'Jiménez Araya'],
    ];

    return Docente::create([
        'cod_empleado' => $codigo,
        'nombre_empleado' => $datos[$codigo][0],
        'apellidos_empleado' => $datos[$codigo][1],
    ]);
}

function grupo01(?Curso $curso = null): Grupo
{
    return Grupo::create([
        'numero_ciclo' => 2,
        'ano' => 2026,
        'numero_grupo' => 1,
        'abierto' => false,
        'curso_id' => ($curso ?? cursoIF6100())->id,
    ]);
}

function estudianteC12345(Docente $consejero): Estudiante
{
    return Estudiante::create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
        'consejero_id' => $consejero->id,
    ]);
}

function matricularEstudiantesGenerados(Grupo $grupo, int $cantidad): void
{
    $consejero = Docente::factory()->create();

    Estudiante::factory()
        ->count($cantidad)
        ->for($consejero, 'consejero')
        ->create()
        ->each(fn (Estudiante $estudiante) => $grupo->matricular($estudiante));
}

function matriculaDe(Grupo $grupo, Estudiante $estudiante): ?EstudianteMatriculado
{
    return EstudianteMatriculado::query()
        ->where('grupo_id', $grupo->id)
        ->where('estudiante_id', $estudiante->id)
        ->first();
}

test('CP-01 registra un grupo con toda su información y su curso', function () {
    $curso = cursoIF6100();

    $creado = Grupo::create([
        'numero_ciclo' => 2,
        'ano' => 2026,
        'numero_grupo' => 1,
        'abierto' => true,
        'curso_id' => $curso->id,
    ]);

    $grupo = Grupo::find($creado->id);

    expect($grupo->numero_ciclo)->toBe(2)
        ->and($grupo->ano)->toBe(2026)
        ->and($grupo->numero_grupo)->toBe(1)
        ->and($grupo->abierto)->toBeTrue()
        ->and($grupo->curso->sigla)->toBe('IF6100')
        ->and($grupo->curso->creditos)->toBe(4)
        ->and(Grupo::count())->toBe(1);
});

test('CP-02 un curso tiene varios grupos y se distinguen los abiertos', function () {
    $curso = cursoIF6100();

    foreach ([1 => true, 2 => true, 3 => false] as $numero => $abierto) {
        Grupo::create([
            'numero_ciclo' => 2,
            'ano' => 2026,
            'numero_grupo' => $numero,
            'abierto' => $abierto,
            'curso_id' => $curso->id,
        ]);
    }

    $curso = Curso::find($curso->id);
    $abiertos = $curso->grupos->where('abierto', true);

    expect($curso->grupos)->toHaveCount(3)
        ->and($abiertos)->toHaveCount(2)
        ->and($abiertos->pluck('numero_grupo')->sort()->values()->all())->toBe([1, 2])
        ->and(Grupo::pluck('curso_id')->unique()->values()->all())->toBe([$curso->id]);
});

test('CP-03 un grupo acepta dos docentes', function () {
    $grupo = grupo01();
    $docente1001 = docenteConCodigo(1001);
    $docente1002 = docenteConCodigo(1002);

    $grupo->asignarDocente($docente1001);
    $grupo->asignarDocente($docente1002);

    expect($grupo->docentes)->toHaveCount(2)
        ->and($grupo->docentes->pluck('cod_empleado')->sort()->values()->all())->toBe([1001, 1002])
        ->and($docente1001->grupos->first()->is($grupo))->toBeTrue()
        ->and(DB::table('grupo_docente')->count())->toBe(2);
});

test('CP-04 un grupo rechaza un tercer docente', function () {
    $grupo = grupo01();
    $grupo->asignarDocente(docenteConCodigo(1001));
    $grupo->asignarDocente(docenteConCodigo(1002));
    $docente1003 = docenteConCodigo(1003);

    expect(fn () => $grupo->asignarDocente($docente1003))->toThrow(DomainException::class);

    expect($grupo->docentes()->count())->toBe(2)
        ->and(DB::table('grupo_docente')->where('docente_id', $docente1003->id)->exists())->toBeFalse();
});

test('CP-05 un grupo con 14 estudiantes no puede abrirse', function () {
    $grupo = grupo01();
    $grupo->asignarDocente(docenteConCodigo(1001));
    matricularEstudiantesGenerados($grupo, 14);

    expect($grupo->puedeAbrirse())->toBeFalse()
        ->and(fn () => $grupo->abrir())->toThrow(DomainException::class)
        ->and($grupo->fresh()->abierto)->toBeFalse();
});

test('CP-06 un grupo con 15 estudiantes y un docente se abre', function () {
    $grupo = grupo01();
    $grupo->asignarDocente(docenteConCodigo(1001));
    matricularEstudiantesGenerados($grupo, 15);

    expect($grupo->puedeAbrirse())->toBeTrue()
        ->and(fn () => $grupo->abrir())->not->toThrow(Throwable::class)
        ->and($grupo->fresh()->abierto)->toBeTrue();
});

test('CP-07 un grupo sin docente no se abre aunque tenga cupo completo', function () {
    $grupo = grupo01();
    matricularEstudiantesGenerados($grupo, 20);

    expect($grupo->puedeAbrirse())->toBeFalse()
        ->and(fn () => $grupo->abrir())->toThrow(DomainException::class)
        ->and($grupo->fresh()->abierto)->toBeFalse();
});

test('CP-08 un estudiante queda ligado a su profesor consejero', function () {
    $docente1001 = docenteConCodigo(1001);

    $estudiante = estudianteC12345($docente1001);

    $estudiante = Estudiante::find($estudiante->id);
    $docente1001 = Docente::find($docente1001->id);

    expect($estudiante->consejero->cod_empleado)->toBe(1001)
        ->and($docente1001->estudiantes)->toHaveCount(1)
        ->and($docente1001->estudiantes->first()->carne)->toBe('C12345')
        ->and($estudiante->consejero_id)->toBe($docente1001->id);
});

test('CP-09 cambiar de consejero reemplaza al anterior, no lo suma', function () {
    $docente1001 = docenteConCodigo(1001);
    $docente1002 = docenteConCodigo(1002);
    $estudiante = estudianteC12345($docente1001);

    $estudiante->asignarConsejero($docente1002);

    $docente1001 = $docente1001->fresh();
    $docente1002 = $docente1002->fresh();

    expect($estudiante->fresh()->consejero->cod_empleado)->toBe(1002)
        ->and($docente1001->estudiantes)->toHaveCount(0)
        ->and($docente1002->estudiantes)->toHaveCount(1)
        ->and(Estudiante::where('carne', 'C12345')->count())->toBe(1)
        ->and($estudiante->fresh()->consejero_id)->toBe($docente1002->id);
});

test('CP-10 al matricular, la nota queda vacía', function () {
    $grupo = grupo01();
    $estudiante = estudianteC12345(docenteConCodigo(1001));

    $grupo->matricular($estudiante);

    $pivote = $grupo->estudiantes->first()->pivot;

    expect($pivote)->toBeInstanceOf(EstudianteMatriculado::class)
        ->and($pivote->nota)->toBeNull()
        ->and(
            DB::table('estudiantes_matriculados')
                ->where('grupo_id', $grupo->id)
                ->where('estudiante_id', $estudiante->id)
                ->count()
        )->toBe(1)
        ->and(DB::table('estudiantes_matriculados')->count())->toBe(1);
});

test('CP-11 registra la nota al finalizar el curso', function () {
    $grupo = grupo01();
    $estudiante = estudianteC12345(docenteConCodigo(1001));
    $otro = Estudiante::factory()->create();
    $grupo->matricular($estudiante);
    $grupo->matricular($otro);

    $grupo->registrarNota($estudiante, 8.5);

    $matricula = matriculaDe($grupo, $estudiante);

    expect($matricula->nota)->toBeFloat()->toBe(8.5)
        ->and($matricula->grupo_id)->toBe($grupo->id)
        ->and($matricula->estudiante_id)->toBe($estudiante->id)
        ->and(matriculaDe($grupo, $otro)->nota)->toBeNull()
        ->and(EstudianteMatriculado::count())->toBe(2);
});

test('CP-12 rechaza notas menores que 0 o mayores que 10', function (int|float $notaInvalida) {
    $grupo = grupo01();
    $estudiante = estudianteC12345(docenteConCodigo(1001));
    $grupo->matricular($estudiante);

    expect(fn () => $grupo->registrarNota($estudiante, $notaInvalida))
        ->toThrow(InvalidArgumentException::class);

    expect(matriculaDe($grupo, $estudiante)->nota)->toBeNull();
})->with([-1, 10.5]);

test('CP-13 un estudiante no se matricula dos veces en el mismo grupo', function () {
    $grupo = grupo01();
    $estudiante = estudianteC12345(docenteConCodigo(1001));
    $grupo->matricular($estudiante);

    expect(fn () => $grupo->matricular($estudiante))->toThrow(QueryException::class);

    expect(
        EstudianteMatriculado::query()
            ->where('grupo_id', $grupo->id)
            ->where('estudiante_id', $estudiante->id)
            ->count()
    )->toBe(1);
});
