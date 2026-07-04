<?php

namespace App\Models;

use CodeIgniter\Model;

class SenceAvanceModel extends Model
{
    protected $table          = 'sence_avances';
    protected $primaryKey     = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields  = [
        'rut_alumno', 'dv_alumno', 'codigo_modulo', 'porcentaje_avance',
        'proceso_id', 'created_at',
    ];
    protected $useTimestamps  = false;

    public function getLastAvance(string $rutAlumno, string $codigoModulo): ?array
    {
        return $this->where('rut_alumno', $rutAlumno)
            ->where('codigo_modulo', $codigoModulo)
            ->orderBy('id', 'DESC')
            ->first();
    }
}
