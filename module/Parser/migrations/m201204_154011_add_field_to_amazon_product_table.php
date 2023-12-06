<?php

use yii\db\Migration;

/**
 * Class m190718_154011_add_index_to_amazon_product_table
 */
class m201204_154011_add_field_to_amazon_product_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'amazon_product';
        $this->addColumn($table, 'last_amazon_product_sync', $this->dateTime());
        $this->createIndex('idx-last_amazon_product_sync', $table, ['last_amazon_product_sync']);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'amazon_product';
        $this->dropColumn($table, 'last_amazon_product_sync');
        $this->dropIndex('idx-last_amazon_product_sync', $table);
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
