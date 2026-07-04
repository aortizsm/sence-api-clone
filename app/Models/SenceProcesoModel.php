<?php

namespace App\Models;

use CodeIgniter\Model;

class SenceProcesoModel extends Model
{
    protected $table          = 'sence_procesos';
    protected $primaryKey     = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields  = [
        'id_proceso', 'rut_otec', 'codigo_oferta', 'codigo_grupo', 'codigo_externo',
        'id_lms', 'estado', 'status_code', 'respuesta_sic', 'observaciones_sic',
        'horario', 'created_at',
    ];
    protected $useTimestamps  = false;
}
