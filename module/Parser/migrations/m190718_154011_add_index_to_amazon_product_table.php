<?php

use yii\db\Migration;

/**
 * Class m190718_154011_add_index_to_amazon_product_table
 */
class m190718_154011_add_index_to_amazon_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'amazon_product';
        $this->createIndex('idx-asin-locale', $table, ['asin', 'locale'], 1);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'amazon_product';
        $this->dropIndex('idx-asin-locale', $table);
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m190718_154011_add_index_to_amazon_product_table cannot be reverted.\n";

        return false;
    }
    */
}
