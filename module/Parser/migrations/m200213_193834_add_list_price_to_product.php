<?php

use yii\db\Migration;

/**
 * Class m200213_193834_add_list_price_to_product
 */
class m200213_193834_add_list_price_to_product extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('product', 'list_price', $this->string(20)->null());
        $this->addColumn('amazon_product', 'list_price', $this->string(20)->null());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('product', 'list_price');
        $this->dropColumn('amazon_product', 'list_price');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200212_183834_add_user_agent_200_qty_field cannot be reverted.\n";

        return false;
    }
    */
}
