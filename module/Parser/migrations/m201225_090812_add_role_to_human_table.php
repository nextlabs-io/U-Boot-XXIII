<?php

use yii\db\Migration;

/**
 * Class m201225_090812_add_role_to_human_table
 */
class m201225_090812_add_role_to_human_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'human';
        $this->addColumn($table, 'role', "ENUM('superadmin','admin','owner','user') NOT NULL DEFAULT 'user'");
        $this->addColumn($table, 'parent_id', $this->integer());
        $this->createIndex('idx-role', $table, 'role');
        $this->createIndex('idx-parent', $table, 'parent_id');
        $this->execute('UPDATE `human` set `role`="admin" where `name`!="Store Owner"');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {

        $table = 'human';
        $this->dropIndex('idx-role', $table);
        $this->dropIndex('idx-parent', $table);
        $this->dropColumn($table, 'role');
        $this->dropColumn($table, 'parent_id');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201225_090812_add_role_to_human_table cannot be reverted.\n";

        return false;
    }
    */
}
