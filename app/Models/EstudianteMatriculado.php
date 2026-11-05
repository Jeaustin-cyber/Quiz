<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Clase de asociación entre Grupo y Estudiante. Guarda la nota del estudiante.
 */
class EstudianteMatriculado extends Pivot
{
    protected $table = 'estudiantes_matriculados';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = ['grupo_id', 'estudiante_id', 'nota'];

    protected $casts = [
        'nota' => 'float',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
