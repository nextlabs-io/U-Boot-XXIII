<?php

use yii\db\Migration;

/**
 * Handles adding offers_data to table `product`.
 */
class m190324_132342_add_offers_data_column_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('product', 'offers_data', $this->text()->null());
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('product', 'offers_data');
    }
}
