<?php

use yii\db\Migration;

/**
 * Handles adding regular_price to table `product`.
 */
class m200129_161102_add_regular_price_column_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('product', 'regular_price', $this->float()->null());
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('product', 'regular_price');
    }
}
