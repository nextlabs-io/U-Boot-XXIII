<?php

use yii\db\Migration;

/**
 * Class m200604_094105_add_fast_track_date_field_to_product_table
 */
class m200604_094105_add_fast_track_date_field_to_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('product', 'fast_track_to', $this->string(60));
        $this->addColumn('product', 'fast_track_from', $this->string(60));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('product', 'fast_track_to');
        $this->dropColumn('product', 'fast_track_from');
    }
}
