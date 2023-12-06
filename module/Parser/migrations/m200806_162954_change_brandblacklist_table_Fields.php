<?php

use yii\db\Migration;

/**
 * Class m200806_162954_change_brandblacklist_table_Fields
 */
class m200806_162954_change_brandblacklist_table_Fields extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'brand_blacklist';
        $this->alterColumn($table, 'locale', 'VARCHAR(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL');
        $this->alterColumn($table, 'brand', 'VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL');
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
