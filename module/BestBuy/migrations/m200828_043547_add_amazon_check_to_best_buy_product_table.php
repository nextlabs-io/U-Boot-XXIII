<?php

use yii\db\Migration;

/**
 * Class m200828_043547_add_amazon_check_to_best_buy_product_table
 */
class m200828_043547_add_amazon_check_to_best_buy_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'product_best_buy';
        $this->addColumn($table, 'amazon_check', $this->integer()->null());
        $this->execute('UPDATE '. $table. ' SET amazon_check = 1 WHERE status not IN(100,50)');
        $this->createIndex('idx_bbp_amazon_check', $table, 'amazon_check');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'product_best_buy';
        $this->dropIndex('idx_bbp_amazon_check', $table);
        $this->dropTable($table);

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200828_043547_add_amazon_check_to_best_buy_product_table cannot be reverted.\n";

        return false;
    }
    */
}
