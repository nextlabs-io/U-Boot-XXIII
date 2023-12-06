<?php

use yii\db\Migration;

/**
 * Class m201005_133734_add_log_field_to_amazon_category_table
 */
class m201005_133734_add_log_field_to_amazon_category_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('amazon_category', 'log', $this->text()->null());
        $this->addColumn('amazon_category', 'parent_id', $this->integer()->null());
        $this->addColumn('product', 'phone_compatibility', $this->string(255)->null());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('amazon_category', 'log');
        $this->dropColumn('amazon_category', 'parent_id');
        $this->dropColumn('product', 'phone_compatibility');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m201005_133734_add_log_field_to_amazon_category_table cannot be reverted.\n";

        return false;
    }
    */
}
