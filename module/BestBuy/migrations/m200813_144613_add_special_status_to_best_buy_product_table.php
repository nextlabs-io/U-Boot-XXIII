<?php

use yii\db\Migration;

/**
 * Class m200813_144613_add_special_status_to_best_buy_product_table
 */
class m200813_144613_add_special_status_to_best_buy_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'product_best_buy';
        $this->addColumn($table, 'bb_status', $this->integer()->null());
        $this->createIndex('idx_bb_status', $table, 'bb_status');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'product_best_buy';
        $this->dropIndex('idx_bb_status', $table);
        $this->dropColumn($table, 'bb_status');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200813_144613_add_special_status_to_best_buy_product_table cannot be reverted.\n";

        return false;
    }
    */
}
