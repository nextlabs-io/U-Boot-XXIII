<?php

use yii\db\Migration;

/**
 * Class m200213_183834_add_list_price_to_camel
 */
class m200213_183834_add_list_price_to_camel extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('product_camel', 'list_price', $this->string(255)->null());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('product_camel', 'list_price');
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
