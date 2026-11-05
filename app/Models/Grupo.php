<?php

namespace App\Models;

use Database\Factories\GrupoFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

class Grupo extends Model
{
    /** @use HasFactory<GrupoFactory> */
    use HasFactory;

    public const MINIMO_ESTUDIANTES_PARA_ABRIR = 15;

    public const MINIMO_DOCENTES = 1;

    public const MAXIMO_DOCENTES = 2;

    public const NOTA_MINIMA = 0;

    public const NOTA_MAXIMA = 10;

    protected $table = 'grupos';

    protected $fillable = ['numero_ciclo', 'ano', 'numero_grupo', 'abierto', 'curso_id'];

    protected $casts = [
        'abierto' => 'boolean',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Docentes que imparten el grupo (de 1 a 2).
     */
    public function docentes(): BelongsToMany
    {
        return $this->belongsToMany(Docente::class, 'grupo_docente')->withTimestamps();
    }

    /**
     * Estudiantes matriculados, con su nota en el pivote.
     */
    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'estudiantes_matriculados')
            ->using(EstudianteMatriculado::class)
            ->withPivot('nota')
            ->withTimestamps();
    }

    /**
     * RN-02: un grupo admite como máximo dos docentes.
     *
     * @throws DomainException si el docente ya está asignado o el grupo ya tiene dos.
     */
    public function asignarDocente(Docente $docente): void
    {
        if ($this->docentes()->whereKey($docente->getKey())->exists()) {
            throw new DomainException('El docente ya está asignado a este grupo.');
        }

        if ($this->docentes()->count() >= self::MAXIMO_DOCENTES) {
            throw new DomainException('Un grupo no puede tener más de '.self::MAXIMO_DOCENTES.' docentes.');
        }

        $this->docentes()->attach($docente->getKey());
        $this->unsetRelation('docentes');
    }

    /**
     * RN-04 y RN-07: la matrícula nace sin nota y el índice único impide repetirla.
     */
    public function matricular(Estudiante $estudiante): void
    {
        $this->estudiantes()->attach($estudiante->getKey());
        $this->unsetRelation('estudiantes');
    }

    /**
     * RN-05 y RN-02: se abre con al menos 15 estudiantes y al menos un docente.
     */
    public function puedeAbrirse(): bool
    {
        return $this->estudiantes()->count() >= self::MINIMO_ESTUDIANTES_PARA_ABRIR
            && $this->docentes()->count() >= self::MINIMO_DOCENTES;
    }

    /**
     * @throws DomainException si el grupo no cumple las condiciones para abrirse.
     */
    public function abrir(): void
    {
        if (! $this->puedeAbrirse()) {
            throw new DomainException(
                'El grupo necesita al menos '.self::MINIMO_ESTUDIANTES_PARA_ABRIR.' estudiantes y '
                .self::MINIMO_DOCENTES.' docente para abrirse.'
            );
        }

        $this->abierto = true;
        $this->save();
    }

    /**
     * RN-06: la nota va de 0 a 10 y solo se registra a estudiantes matriculados.
     *
     * @throws InvalidArgumentException si la nota está fuera de rango.
     * @throws DomainException si el estudiante no está matriculado en el grupo.
     */
    public function registrarNota(Estudiante $estudiante, int|float $nota): void
    {
        if ($nota < self::NOTA_MINIMA || $nota > self::NOTA_MAXIMA) {
            throw new InvalidArgumentException(
                'La nota debe estar entre '.self::NOTA_MINIMA.' y '.self::NOTA_MAXIMA.'.'
            );
        }

        $matricula = EstudianteMatriculado::query()
            ->where('grupo_id', $this->getKey())
            ->where('estudiante_id', $estudiante->getKey())
            ->first();

        if ($matricula === null) {
            throw new DomainException('El estudiante no está matriculado en este grupo.');
        }

        $matricula->nota = $nota;
        $matricula->save();
    }
}
