<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSenceProcesoRegistros extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                      => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'proceso_id'              => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true],
            'rut_otec'                => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'dv_otec'                 => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],
            'oferta_cod'              => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'seccion_cod'             => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'rut_alumno'              => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'dv_alumno'               => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],
            'tiempo_conectividad'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'estado'                  => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],
            'porcentaje_avance'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'fecha_inicio'            => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'fecha_fin'               => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'fecha_ejecucion'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'mod_cod'                 => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'mod_tiempo_conectividad' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'mod_estado'              => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],
            'mod_porcentaje_avance'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'mod_fecha_inicio'        => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'mod_fecha_fin'           => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'mod_obligatorio'         => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'mod_nota'                => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'act_cod'                 => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'act_estado'              => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'estado_registro'         => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],
            'obs_registro'            => ['type' => 'TEXT', 'null' => true],
            'id_proceso_externo'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('sence_proceso_registros');
    }

    public function down()
    {
        $this->forge->dropTable('sence_proceso_registros');
    }
}
