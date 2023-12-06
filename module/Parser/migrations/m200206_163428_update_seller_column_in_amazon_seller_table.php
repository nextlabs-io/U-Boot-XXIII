<?php

use yii\db\Migration;

/**
 * Class m200206_163428_update_seller_column_in_amazon_seller_table
 */
class m200206_163428_update_seller_column_in_amazon_seller_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('product', 'merchantId', $this->string(30));
        $this->alterColumn('amazon_seller', 'seller', $this->string(30));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200206_163428_update_seller_column_in_amazon_seller_table cannot be reverted.\n";
        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200206_163428_update_seller_column_in_amazon_seller_table cannot be reverted.\n";

        return false;
    }
    */
}
