<?php

use yii\db\Migration;

/**
 * Class m191110_125850_alter_proxy_table_proxy_port_field
 */
class m191110_125850_alter_proxy_table_proxy_port_field extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('proxy', 'port', 'INT NOT NULL');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191110_125850_alter_proxy_table_proxy_port_field cannot be reverted.\n";
        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m191110_125850_alter_proxy_table_proxy_port_field cannot be reverted.\n";

        return false;
    }
    */
}
