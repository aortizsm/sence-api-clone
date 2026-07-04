<?php

namespace App\Models;

use CodeIgniter\Model;

class SenceProcesoRegistroModel extends Model
{
    protected $table          = 'sence_proceso_registros';
    protected $primaryKey     = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields  = [
        'proceso_id', 'rut_otec', 'dv_otec', 'oferta_cod', 'seccion_cod',
        'rut_alumno', 'dv_alumno', 'tiempo_conectividad', 'estado',
        'porcentaje_avance', 'fecha_inicio', 'fecha_fin', 'fecha_ejecucion',
        'mod_cod', 'mod_tiempo_conectividad', 'mod_estado', 'mod_porcentaje_avance',
        'mod_fecha_inicio', 'mod_fecha_fin', 'mod_obligatorio', 'mod_nota',
        'act_cod', 'act_estado', 'estado_registro', 'obs_registro',
        'id_proceso_externo', 'created_at',
    ];
    protected $useTimestamps  = false;
}
