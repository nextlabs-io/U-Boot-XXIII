<?php

use yii\db\Migration;

/**
 * Handles adding combined_descrription to table `product`.
 */
class m190511_070005_add_combined_description_column_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('product', 'combined_description', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('product', 'combined_description');
    }
}
