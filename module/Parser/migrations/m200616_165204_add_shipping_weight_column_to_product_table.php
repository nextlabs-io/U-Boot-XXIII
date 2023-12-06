<?php

use yii\db\Migration;

/**
 * Handles adding shipping_weight to table `product`.
 */
class m200616_165204_add_shipping_weight_column_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('product', 'shipping_weight', $this->float()->null());
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('product', 'shipping_weight');
    }
}
