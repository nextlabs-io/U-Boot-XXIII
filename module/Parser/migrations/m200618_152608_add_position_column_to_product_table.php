<?php

use yii\db\Migration;

/**
 * Handles adding position to table `product`.
 */
class m200618_152608_add_position_column_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->addColumn('product', 'fast_track_days', $this->string(60)->null());
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropColumn('product', 'fast_track_days');
    }
}
