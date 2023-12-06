<?php

use yii\db\Migration;

/**
 * Handles adding upc_list_ean_list to table `product`.
 */
class m191027_170913_add_upc_list_ean_list_columns_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('product', 'ean_list', $this->text()->null());
        $this->addColumn('product', 'upc_list', $this->text()->null());
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('product', 'ean_list');
        $this->dropColumn('product', 'upc_list');
    }
}
