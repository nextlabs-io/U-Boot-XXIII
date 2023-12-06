<?php

use yii\db\Migration;

/**
 * Handles adding upc_list_ean_list to table `amazon_product`.
 */
class m191027_165142_add_upc_list_ean_list_columns_to_amazon_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('amazon_product', 'ean_list', $this->text()->null());
        $this->addColumn('amazon_product', 'upc_list', $this->text()->null());
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('amazon_product', 'ean_list');
        $this->dropColumn('amazon_product', 'upc_list');
    }
}
