<?php

use yii\db\Migration;

/**
 * Class m190529_042815_add_dimension_columns_amazon_product_table
 */
class m190529_042815_add_dimension_columns_amazon_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('amazon_product', 'item_dimensions', $this->string(255));
        $this->addColumn('amazon_product', 'package_dimensions', $this->string(255));
        $this->addColumn('amazon_product', 'size', $this->string(255));
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('amazon_product', 'item_dimensions');
        $this->dropColumn('amazon_product', 'package_dimensions');
        $this->dropColumn('amazon_product', 'size');

    }


}
