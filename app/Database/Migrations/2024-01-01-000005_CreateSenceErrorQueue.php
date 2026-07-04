<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSenceErrorQueue extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                   => ['type' => 'INTEGER', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'endpoint'             => ['type' => 'VARCHAR', 'constraint' => 50],
            'codigo_error'         => ['type' => 'VARCHAR', 'constraint' => 10],
            'peticiones_restantes' => ['type' => 'INTEGER', 'default' => 0],
            'glosa_error'          => ['type' => 'TEXT', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('endpoint');
        $this->forge->createTable('sence_error_queue');
    }

    public function down()
    {
        $this->forge->dropTable('sence_error_queue');
    }
}
