<?php

use yii\db\Migration;

/**
 * Class m201130_062243_change_cdiscount_product_table2
 */
class m201130_062243_change_cdiscount_product_table2 extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'cdiscount_product';
        $this->addColumn($table, 'amazon_product_id', $this->integer());
        $this->createIndex('product_id-idx', $table, 'amazon_product_id');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'cdiscount_product';
        $this->dropColumn($table, 'amazon_product_id');
        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201130_062242_change_cdiscount_product_table cannot be reverted.\n";

        return false;
    }
    */
}
