<?php

use yii\db\Migration;

/**
 * Handles adding position to table `product`.
 */
class m200618_104712_add_position_column_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('product', 'product_page_price', $this->float()->null());
        $this->addColumn('product', 'offer_page_price', $this->float()->null());
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('product', 'product_page_price');
        $this->dropColumn('product', 'offer_page_price');
    }
}
