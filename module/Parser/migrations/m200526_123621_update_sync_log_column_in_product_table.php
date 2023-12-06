<?php

use yii\db\Migration;

/**
 * Class m200526_123621_update_sync_log_column_in_product_table
 */
class m200526_123621_update_sync_log_column_in_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('product', 'sync_log', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->alterColumn('product', 'sync_log', $this->string(500));
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200526_123621_update_sync_log_column_in_product_table cannot be reverted.\n";

        return false;
    }
    */
}
