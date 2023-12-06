<?php

use yii\db\Migration;

/**
 * Class m200119_123457_change_merchantId_field_in_product_table
 */
class m200119_123457_change_merchantId_field_in_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('product', 'merchantId', $this->string(40));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->alterColumn('product', 'merchantId', $this->string(20));
        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200119_123457_change_merchantId_field_in_product_table cannot be reverted.\n";

        return false;
    }
    */
}
