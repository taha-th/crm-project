<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_285 extends CI_Migration
{

    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 5,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ),
            'lead_id' => array(
                'type' => 'INT',
                'constraint' => 5,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ),
            'source' => array(
                'type' => 'VARCHAR',
                'constraint' => '100',
            ),
            'status' => array(
                'type' => 'INT',
                'constraint' => 2,
                'default' => 1,
            ),
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->add_key('lead_id');
        $this->dbforge->create_table('leads_images');
    }

    public function down()
    {
        $this->dbforge->drop_table('leads_images');
    }
}
