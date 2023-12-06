<?php

use yii\db\Migration;

/**
 * Class m200930_064159_alter_amazon_category_group_index_extend_title
 */
class m200930_064159_alter_amazon_category_group_index_extend_title extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('amazon_category', 'title', $this->text());

        $this->addColumn('user_agent', 'type', $this->string(20)->null());
        $this->createIndex('idx-ua-type', 'user_agent', 'type');
        $this->execute("update user_agent set `type` = 'default'");
        $this->execute("update user_agent set `type` = 'whatsap' where `value` like('%whatsap%')");
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200930_064159_alter_amazon_category_group_index_extend_title cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200930_064159_alter_amazon_category_group_index_extend_title cannot be reverted.\n";

        return false;
    }
    */
}
