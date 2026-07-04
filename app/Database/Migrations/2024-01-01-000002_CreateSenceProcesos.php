<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSenceProcesos extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'id_proceso'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'rut_otec'          => ['type' => 'VARCHAR', 'constraint' => 20],
            'codigo_oferta'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'codigo_grupo'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'codigo_externo'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'id_lms'            => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => '0'],
            'estado'            => ['type' => 'VARCHAR', 'constraint' => 2, 'default' => '2'],
            'status_code'       => ['type' => 'VARCHAR', 'constraint' => 2, 'default' => '0'],
            'respuesta_sic'     => ['type' => 'TEXT', 'null' => true],
            'observaciones_sic' => ['type' => 'TEXT', 'null' => true],
            'horario'           => ['type' => 'DATETIME', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('sence_procesos');
    }

    public function down()
    {
        $this->forge->dropTable('sence_procesos');
    }
}
