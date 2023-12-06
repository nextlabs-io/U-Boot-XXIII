<?php

use yii\db\Migration;

/**
 * Class m200212_183834_add_user_agent_200_qty_field
 */
class m200212_183834_add_user_agent_200_qty_field extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('user_agent', 'success_qty', $this->integer()->null());
        $this->createIndex('idx-ua-success_qty', 'user_agent', 'success_qty');

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200212_183834_add_user_agent_200_qty_field cannot be reverted.\n";
        $this->dropIndex('idx-ua-success_qty', 'user_agent');
        $this->dropColumn('user_agent', 'success_qty');

        return false;
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
