<?php

use yii\db\Migration;

/**
 * Class m201027_052349_add_product_page_import_fee_to_product_table
 */
class m201027_052349_add_product_page_import_fee_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('product', 'product_page_import_fee', $this->float()->null());

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('product', 'product_page_import_fee');

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201027_052349_add_product_page_import_fee_to_product_table cannot be reverted.\n";

        return false;
    }
    */
}
