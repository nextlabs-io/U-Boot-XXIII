<?php

use yii\db\Migration;

/**
 * Class m200226_164354_add_thirdparty_highest_price_to_3_tables
 */
class m200226_164354_add_thirdparty_highest_price_to_3_tables extends Migration
{
    public function safeUp()
    {
        $fields = ['thirdparty_highest'];
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
        $fields = ['thirdparty_highest'];
        foreach ($fields as $field) {
            $this->dropColumn('product', $field);
            $this->dropColumn('amazon_product', $field);
            $this->dropColumn('product_camel', $field);
        }

    }

}
