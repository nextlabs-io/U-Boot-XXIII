<?php

use yii\db\Migration;

/**
 * Class m200226_164257_add_amazon_highest_price_to_3_tables
 */
class m200226_164257_add_amazon_highest_price_to_3_tables extends Migration
{
    public function safeUp()
    {
        $fields = ['amazon_highest'];
        foreach ($fields as $field) {
            $this->addColumn('product_camel', $field, $this->string(20)->after('ean')->null());
            $this->addColumn('product', $field, $this->string(20)->null());
            $this->addColumn('amazon_product', $field, $this->string(20)->null());
        }
    }


    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $fields = ['amazon_highest'];
        foreach ($fields as $field) {
            $this->dropColumn('product', $field);
            $this->dropColumn('amazon_product', $field);
            $this->dropColumn('product_camel', $field);
        }

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200226_164257_add_amazon_highest_price_to_3_tables cannot be reverted.\n";

        return false;
    }
    */
}
