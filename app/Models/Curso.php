<?php

namespace App\Models;

use Database\Factories\CursoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    /** @use HasFactory<CursoFactory> */
    use HasFactory;

    protected $fillable = ['sigla', 'nombre_curso', 'creditos'];

    /**
     * Grupos que se han abierto o creado para este curso.
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }
}
