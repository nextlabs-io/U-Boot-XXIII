<?php

use yii\db\Migration;

/**
 * Handles adding dimension to table `product`.
 */
class m200616_161834_add_dimension_columns_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('product', 'shipping_width', $this->float()->null());
        $this->addColumn('product', 'shipping_length', $this->float()->null());
        $this->addColumn('product', 'shipping_height', $this->float()->null());
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('product', 'shipping_width');
        $this->dropColumn('product', 'shipping_length');
        $this->dropColumn('product', 'shipping_height');

    }
}
