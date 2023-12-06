<?php

use yii\db\Migration;

/**
 * Class m200806_162954_change_brandblacklist_table_Fields
 */
class m200806_162954_change_product_table_Fields extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'product';
        $this->alterColumn($table, 'title', 'VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200806_162954_change_brandblacklist_table_Fields cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200806_162954_change_brandblacklist_table_Fields cannot be reverted.\n";

        return false;
    }
    */
}
