<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSenceSesiones extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'rut_otec'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'token'           => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'cod_sence'       => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'codigo_curso'    => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'linea_capacitacion' => ['type' => 'INTEGER', 'null' => true],
            'run_alumno'      => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'id_sesion_alumno' => ['type' => 'VARCHAR', 'constraint' => 149, 'null' => true],
            'id_sesion_sence' => ['type' => 'VARCHAR', 'constraint' => 149, 'null' => true],
            'url_retoma'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'url_error'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'estado'          => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'activa'],
            'glosa_error'     => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
            'fecha_inicio'    => ['type' => 'DATETIME', 'null' => true],
            'fecha_cierre'    => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('sence_sesiones');
    }

    public function down()
    {
        $this->forge->dropTable('sence_sesiones');
    }
}
