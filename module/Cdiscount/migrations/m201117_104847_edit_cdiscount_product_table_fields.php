<?php

use yii\db\Migration;

/**
 * Class m201117_104847_edit_cdiscount_product_table_fields
 */
class m201117_104847_edit_cdiscount_product_table_fields extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $table = 'cdiscount_product';
        $this->addColumn($table, 'amazon_check', $this->integer()->null());
        $this->addColumn($table, 'keepa_check', $this->integer()->null());
        $this->createIndex('idx-cdp-amazon_check', $table, 'amazon_check');
        $this->createIndex('idx-cdp-keepa_check', $table, 'keepa_check');
        $this->createIndex('idx-cdp-amazon-product', $table, ['asin', 'locale']);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $table = 'cdiscount_product';
        $this->dropIndex('idx-cdp-amazon-product', $table);
        $this->dropIndex('idx-cdp-amazon_check', $table);
        $this->dropIndex('idx-cdp-keepa_check', $table);
        $this->dropColumn($table, 'amazon_check');
        $this->dropColumn($table, 'keepa_check');

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201117_104847_edit_cdiscount_product_table_fields cannot be reverted.\n";

        return false;
    }
    */
}
