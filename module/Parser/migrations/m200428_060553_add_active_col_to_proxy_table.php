<?php

use yii\db\Migration;

/**
 * Class m200428_060553_add_active_col_to_proxy_table
 */
class m200428_060553_add_active_col_to_proxy_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $fields = ['active'];
        foreach ($fields as $field) {
            $this->addColumn('proxy', $field, 'BIT NOT NULL DEFAULT 1');
        }

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $fields = ['active'];
        foreach ($fields as $field) {
            $this->dropColumn('proxy', $field);
        }
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200428_060553_add_active_col_to_proxy_table cannot be reverted.\n";

        return false;
    }
    */
}
