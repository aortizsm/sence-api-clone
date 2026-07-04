<?php

namespace App\Models;

use CodeIgniter\Model;

class SenceSesionModel extends Model
{
    protected $table          = 'sence_sesiones';
    protected $primaryKey     = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields  = [
        'rut_otec', 'token', 'cod_sence', 'codigo_curso', 'linea_capacitacion',
        'run_alumno', 'id_sesion_alumno', 'id_sesion_sence', 'url_retoma',
        'url_error', 'estado', 'glosa_error', 'fecha_inicio', 'fecha_cierre',
        'created_at',
    ];
    protected $useTimestamps  = false;

    public function findBySesionSence(string $idSesionSence): ?array
    {
        return $this->where('id_sesion_sence', $idSesionSence)->first();
    }
}
