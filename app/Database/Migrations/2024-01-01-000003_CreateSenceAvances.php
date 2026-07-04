<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSenceAvances extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'rut_alumno'        => ['type' => 'VARCHAR', 'constraint' => 10],
            'dv_alumno'         => ['type' => 'VARCHAR', 'constraint' => 2],
            'codigo_modulo'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'porcentaje_avance' => ['type' => 'DECIMAL', 'constraint' => '10,5'],
            'proceso_id'        => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['rut_alumno', 'codigo_modulo']);
        $this->forge->createTable('sence_avances');
    }

    public function down()
    {
        $this->forge->dropTable('sence_avances');
    }
}
