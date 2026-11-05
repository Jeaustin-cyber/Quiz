<?php

namespace App\Models;

use Database\Factories\EstudianteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Estudiante extends Model
{
    /** @use HasFactory<EstudianteFactory> */
    use HasFactory;

    protected $fillable = ['carne', 'nombre', 'apellidos', 'consejero_id'];

    /**
     * Profesor consejero. Un estudiante tiene uno solo.
     */
    public function consejero(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'consejero_id');
    }

    /**
     * Grupos en los que el estudiante está matriculado, con su nota en el pivote.
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'estudiantes_matriculados')
            ->using(EstudianteMatriculado::class)
            ->withPivot('nota')
            ->withTimestamps();
    }

    /**
     * Cambia el consejero: reemplaza al anterior, no se suma.
     */
    public function asignarConsejero(Docente $docente): void
    {
        $this->consejero()->associate($docente);
        $this->save();
    }
}
