<?php

use yii\db\Migration;

/**
 * Class m200813_192804_add_index_bb_category_to_best_buy_product_table
 */
class m200813_192804_add_index_bb_category_to_best_buy_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'product_best_buy';
        $this->createIndex('idx_bb_category', $table, 'bb_category');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'product_best_buy';
        $this->dropIndex('idx_bb_category', $table);
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200813_192804_add_index_bb_category_to_best_buy_product_table cannot be reverted.\n";

        return false;
    }
    */
}
